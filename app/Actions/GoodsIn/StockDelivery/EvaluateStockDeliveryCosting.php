<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateCosts;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Actions\Inventory\OrgStock\Stock\RebuildOrgStockHistoriesSince;
use App\Models\GoodsIn\StockDeliveryCost;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsAction;
use OwenIt\Auditing\Events\AuditCustom;

class EvaluateStockDeliveryCosting
{
    use AsAction;

    /**
     * A reopened costing only closes from FinishStockDeliveryCosting ($finishing), never from a checklist or deposit save.
     */
    public function handle(StockDelivery $stockDelivery, bool $finishing = false): StockDelivery
    {
        $stockDelivery->refresh();

        $stockDelivery->items()
            ->whereNull('cost_items')
            ->update([
                'cost_items' => DB::raw('net_amount'),
                'cost_total' => DB::raw('net_amount + coalesce(cost_extra, 0) + coalesce(cost_shipping, 0) + coalesce(cost_duties, 0) + coalesce(cost_tax, 0)'),
            ]);

        $costs   = $stockDelivery->costs()->get();
        $amounts = self::splitAmounts($costs);

        self::redistribute($stockDelivery, $amounts);

        $isReopened = Arr::has($stockDelivery->data, 'costing_reopened');
        $isCosted   = $stockDelivery->parent_type === 'OrgPartner'
            || ($this->isCosted($costs) && self::unbalancedHandSplits($stockDelivery, $amounts) === [] && (!$isReopened || $finishing));

        $stockDelivery->update(['is_costed' => $isCosted]);

        $repricedSince = [];
        if ($stockDelivery->is_costed) {
            $stockDelivery->items()
                ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
                ->update(['is_costed' => true]);

            $repricedSince = RepriceStockDeliveryOrgStockMovements::run($stockDelivery);
        }

        StockDeliveriesHydrateCosts::run($stockDelivery);

        $stockDelivery->refresh();

        if ($stockDelivery->is_costed && Arr::has($stockDelivery->data, 'costing_reopened')) {
            $this->closeReopenedCosting($stockDelivery, $repricedSince);
        }

        return $stockDelivery;
    }

    /**
     * Shares shipping, duty and extra over the items that take part, except the costs split by hand.
     *
     * @param array<string, float>|null $amounts
     */
    public static function redistribute(StockDelivery $stockDelivery, ?array $amounts = null): void
    {
        $amounts ??= self::splitAmounts($stockDelivery->costs()->get());
        $handSplit     = Arr::get($stockDelivery->data, 'costing_hand_split', []);
        $keptHandSplit = [];

        foreach ($amounts as $field => $amount) {
            if (array_key_exists($field, $handSplit) && $handSplit[$field] === self::cents($amount)) {
                $keptHandSplit[$field] = $handSplit[$field];
                continue;
            }
            DistributeStockDeliveryExtraCost::distribute(
                $stockDelivery,
                $field,
                $amount,
                $field === 'cost_shipping' ? DistributeStockDeliveryExtraCost::DISTRIBUTION_BY_WEIGHT : DistributeStockDeliveryExtraCost::DISTRIBUTION_BY_VALUE
            );
        }

        if ($keptHandSplit !== $handSplit) {
            $stockDelivery->update(['data' => array_merge($stockDelivery->data, ['costing_hand_split' => $keptHandSplit])]);
        }
    }

    /**
     * @return array<string, float> each split cost's total in the delivery currency, by line field
     */
    public static function splitAmounts(Collection $costs): array
    {
        $amounts = [];
        foreach ([StockDeliveryCostTypeEnum::SHIPPING, StockDeliveryCostTypeEnum::DUTY] as $type) {
            $row                            = $costs->firstWhere('type', $type);
            $amounts[$type->itemCostField()] = $row && !$row->is_na ? $row->amountInDeliveryCurrency() : 0;
        }

        $amounts['cost_extra'] = $costs
            ->where('type', StockDeliveryCostTypeEnum::EXTRA)
            ->filter(fn (StockDeliveryCost $cost) => !$cost->is_na)
            ->sum(fn (StockDeliveryCost $cost) => $cost->amountInDeliveryCurrency());

        return $amounts;
    }

    /**
     * Costs split by hand whose lines do not add up to the cost: the costing can not be finished until they do.
     *
     * @return array<string, array{allocated: float, amount: float}>
     */
    public static function unbalancedHandSplits(StockDelivery $stockDelivery, ?array $amounts = null): array
    {
        $handSplit = Arr::get($stockDelivery->data, 'costing_hand_split', []);
        if (!$handSplit) {
            return [];
        }

        $amounts ??= self::splitAmounts($stockDelivery->costs()->get());
        $unbalanced = [];
        foreach (array_keys($handSplit) as $field) {
            $allocated = (float) $stockDelivery->items()
                ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
                ->sum($field);

            if (self::cents($allocated) !== self::cents($amounts[$field] ?? 0)) {
                $unbalanced[$field] = ['allocated' => round($allocated, 2), 'amount' => round($amounts[$field] ?? 0, 2)];
            }
        }

        return $unbalanced;
    }

    public static function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * @param array<int, string> $repricedSince
     */
    private function closeReopenedCosting(StockDelivery $stockDelivery, array $repricedSince): void
    {
        $reopened = Arr::get($stockDelivery->data, 'costing_reopened');

        $stockDelivery->auditEvent     = 'costing_updated';
        $stockDelivery->isCustomEvent  = true;
        $stockDelivery->auditCustomOld = Arr::get($reopened, 'costs', []);
        $stockDelivery->auditCustomNew = ReopenStockDeliveryCosting::costsSnapshot($stockDelivery);
        Event::dispatch(new AuditCustom($stockDelivery));
        $stockDelivery->isCustomEvent  = false;
        $stockDelivery->auditCustomOld = $stockDelivery->auditCustomNew = [];

        $stockDelivery->update(['data' => Arr::except($stockDelivery->data, 'costing_reopened')]);

        foreach ($repricedSince as $orgStockId => $fromDate) {
            RebuildOrgStockHistoriesSince::dispatch($orgStockId, $fromDate)->delay(60)->afterCommit();
        }
    }

    private function isCosted($costs): bool
    {
        $agentInvoice = $costs->firstWhere('type', StockDeliveryCostTypeEnum::AGENT_INVOICE);
        if (!$agentInvoice || !$agentInvoice->received_at) {
            return false;
        }

        foreach ([StockDeliveryCostTypeEnum::SHIPPING, StockDeliveryCostTypeEnum::DUTY] as $type) {
            $row = $costs->firstWhere('type', $type);
            if (!$row || (!$row->received_at && !$row->is_na)) {
                return false;
            }
        }

        return $costs
            ->where('type', StockDeliveryCostTypeEnum::EXTRA)
            ->every(fn (StockDeliveryCost $cost) => $cost->received_at || $cost->is_na);
    }
}
