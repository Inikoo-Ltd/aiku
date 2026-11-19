<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateCosts;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
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

        self::followTariffCodes($stockDelivery);

        $costs   = $stockDelivery->costs()->get();
        $amounts = self::splitAmounts($costs);

        self::redistribute($stockDelivery, $amounts);

        $isReopened = Arr::has($stockDelivery->data, 'costing_reopened');
        $isCosted   = ($stockDelivery->parent_type === 'OrgPartner' ? $this->isPartnerCosted($stockDelivery, $costs, $finishing) : $this->isCosted($costs))
            && self::unbalancedHandSplits($stockDelivery, $amounts) === []
            && self::costsWithoutLines($stockDelivery, $costs)->isEmpty()
            && (!$isReopened || $finishing);

        $stockDelivery->update([
            'is_costed' => $isCosted,
            ...($isCosted && !$stockDelivery->is_costed ? ['data' => array_merge($stockDelivery->data ?? [], ['costed' => ['at' => now()->toIso8601String(), 'user_id' => auth()->id()]])] : []),
        ]);

        $repricedSince = [];
        if ($stockDelivery->is_costed) {
            $stockDelivery->items()
                ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
                ->update(['is_costed' => true]);

            $repricedSince = RepriceStockDeliveryOrgStockMovements::run($stockDelivery);
        }

        foreach ($repricedSince as $orgStockId => $fromDate) {
            RebuildOrgStockHistoriesSince::dispatch($orgStockId, $fromDate)->delay(60)->afterCommit();
        }

        StockDeliveriesHydrateCosts::run($stockDelivery);

        $stockDelivery->refresh();

        if ($stockDelivery->is_costed && Arr::has($stockDelivery->data, 'costing_reopened')) {
            $this->closeReopenedCosting($stockDelivery);
        }

        return $stockDelivery;
    }

    /**
     * Shares shipping (by weight), duty (by customs line when the declaration is entered) and extra over the
     * items that take part, except the costs split by hand. A cost that belongs to some lines (one SKO's packaging,
     * an anti-dumping duty on some tariff codes) goes to those lines only, on top of their share of the rest.
     *
     * @param array<string, float>|null $amounts
     */
    public static function redistribute(StockDelivery $stockDelivery, ?array $amounts = null): void
    {
        self::followTariffCodes($stockDelivery);

        $costs         = $stockDelivery->costs()->get();
        $amounts ??= self::splitAmounts($costs);
        $ownLineAmounts = self::lineAmounts($stockDelivery, $costs);
        $handSplit     = Arr::get($stockDelivery->data, 'costing_hand_split', []);
        $keptHandSplit = [];

        foreach ($amounts as $field => $amount) {
            if (array_key_exists($field, $handSplit) && $handSplit[$field] === self::cents($amount)) {
                $keptHandSplit[$field] = $handSplit[$field];
                continue;
            }
            $lineAmounts = $ownLineAmounts[$field] ?? [];
            DistributeStockDeliveryExtraCost::distribute(
                $stockDelivery,
                $field,
                $amount - array_sum($lineAmounts),
                match (true) {
                    $field === 'cost_shipping' => DistributeStockDeliveryExtraCost::DISTRIBUTION_BY_WEIGHT,
                    $field === 'cost_duties' && $stockDelivery->customsLines()->exists() => DistributeStockDeliveryExtraCost::DISTRIBUTION_BY_CUSTOMS_LINE,
                    default => DistributeStockDeliveryExtraCost::DISTRIBUTION_BY_VALUE,
                },
                $lineAmounts
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

        $amounts['cost_extra'] = 0;

        foreach ($costs->where('type', StockDeliveryCostTypeEnum::EXTRA)->reject(fn (StockDeliveryCost $cost) => $cost->is_na) as $cost) {
            $amounts[$cost->lineCostField()] += $cost->amountInDeliveryCurrency();
        }

        return $amounts;
    }

    /**
     * Extras that belong to some lines of the delivery, by line field and line, in the delivery currency: the
     * packaging of one SKO goes whole to its line, an anti-dumping duty is split by value among the lines of the
     * tariff codes it was picked for. Those lines take them instead of sharing them with the others.
     *
     * @return array<string, array<int, float>>
     */
    public static function lineAmounts(StockDelivery $stockDelivery, Collection $costs): array
    {
        $lineAmounts = [];

        foreach ($costs->where('type', StockDeliveryCostTypeEnum::EXTRA)->reject(fn (StockDeliveryCost $cost) => $cost->is_na) as $cost) {
            /** @var StockDeliveryCost $cost */
            $shares = match (true) {
                (bool) $cost->stock_delivery_item_id => [$cost->stock_delivery_item_id => $cost->amountInDeliveryCurrency()],
                $cost->appliesByTariffCode()         => DistributeStockDeliveryExtraCost::sharesAmong($stockDelivery, $cost->applies_to_item_ids ?? [], $cost->amountInDeliveryCurrency()),
                default                              => [],
            };

            foreach ($shares as $itemId => $share) {
                $lineAmounts[$cost->lineCostField()][$itemId] = ($lineAmounts[$cost->lineCostField()][$itemId] ?? 0) + $share;
            }
        }

        return array_map(fn (array $amounts) => array_map(fn (float $amount) => round($amount, 2), $amounts), $lineAmounts);
    }

    /**
     * Until a delivery is costed for the first time, a row picked by tariff code follows the lines that carry those
     * codes now: a line added at goods in, or a trade unit whose tariff code was corrected. Once costed the lines
     * stay as they were, so a later change never moves money in a closed costing; picking the codes again on an
     * updated costing takes the lines of that moment.
     */
    public static function followTariffCodes(StockDelivery $stockDelivery): void
    {
        if (Arr::has($stockDelivery->data ?? [], 'costed')) {
            return;
        }

        $stockDelivery->costs()->whereNotNull('applies_to_tariff_codes')->get()
            ->filter(fn (StockDeliveryCost $cost) => $cost->appliesByTariffCode())
            ->each(function (StockDeliveryCost $cost) use ($stockDelivery) {
                $itemIds = DistributeStockDeliveryExtraCost::itemIdsWithTariffCodes($stockDelivery, $cost->applies_to_tariff_codes);
                if ($itemIds !== ($cost->applies_to_item_ids ?? [])) {
                    $cost->update(['applies_to_item_ids' => $itemIds]);
                }
            });
    }

    /**
     * Rows that must go to some lines only but have none of their lines left in the delivery: an anti-dumping duty
     * whose tariff codes were not picked, or whose lines were all cancelled or not received. They keep the costing
     * open, because spreading them over the whole delivery is exactly what they must not do.
     *
     * @return Collection<int, StockDeliveryCost>
     */
    public static function costsWithoutLines(StockDelivery $stockDelivery, ?Collection $costs = null): Collection
    {
        $costs ??= $stockDelivery->costs()->get();

        return $costs
            ->filter(fn (StockDeliveryCost $cost) => $cost->appliesByTariffCode() && !$cost->is_na && (float) $cost->amount != 0)
            ->filter(fn (StockDeliveryCost $cost) => DistributeStockDeliveryExtraCost::sharesAmong($stockDelivery, $cost->applies_to_item_ids ?? [], 1) === [])
            ->values();
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
    private function closeReopenedCosting(StockDelivery $stockDelivery): void
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
    }

    /**
     * A partner delivery costs its goods from the partner order, so it needs no invoice, shipping or duty row. The
     * receiving organisation's own landed costs (import duty, freight, extras) go on top (INI-399): the costing closes
     * by itself when the delivery is placed, with every local row it has received or N/A, and is open until then so
     * the duty paid at customs can be added.
     */
    private function isPartnerCosted(StockDelivery $stockDelivery, Collection $costs, bool $finishing): bool
    {
        return ($finishing || $stockDelivery->state === StockDeliveryStateEnum::PLACED)
            && $costs
                ->where('type', '!=', StockDeliveryCostTypeEnum::AGENT_INVOICE)
                ->every(fn (StockDeliveryCost $cost) => $cost->received_at || $cost->is_na);
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
