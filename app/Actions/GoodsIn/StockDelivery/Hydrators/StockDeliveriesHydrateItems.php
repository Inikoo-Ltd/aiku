<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 00:53:14 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery\Hydrators;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemDiscrepancyEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class StockDeliveriesHydrateItems implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(StockDelivery $stockDelivery): string
    {
        return $stockDelivery->id;
    }

    public function handle(StockDelivery $stockDelivery): void
    {
        $weights = $this->getWeights($stockDelivery);

        $stateCounts = $stockDelivery->items()
            ->groupBy('state')
            ->selectRaw('state, count(*) as aggregate')
            ->pluck('aggregate', 'state');

        $items = (int) $stateCounts->sum();

        $itemsNet = $stockDelivery->items()->sum('net_amount');
        $extras   = (float) $stockDelivery->cost_extra
            + (float) $stockDelivery->cost_shipping
            + (float) $stockDelivery->cost_duties
            + (float) $stockDelivery->cost_tax;

        $discrepancy = $this->getDeliveryDiscrepancy($stockDelivery);

        $stats = [
            'number_stock_delivery_items'                  => $items,
            'number_stock_delivery_items_except_cancelled' => $items - (int) Arr::get($stateCounts, StockDeliveryItemStateEnum::CANCELLED->value, 0),
            'number_stock_delivery_items_under_delivered'  => $discrepancy['under_delivered'],
            'number_stock_delivery_items_over_delivered'   => $discrepancy['over_delivered'],
            'number_stock_delivery_items_possible_unit_mismatch' => $discrepancy['possible_unit_mismatch'],
            'gross_weight'                                 => $weights['gross_weight'],
            'net_weight'                                   => $weights['net_weight'],
        ];

        if ($stockDelivery->state !== StockDeliveryStateEnum::PLACED) {
            $stats['cost_items'] = $itemsNet;
            $stats['cost_total'] = $itemsNet + $extras;
        }

        foreach (StockDeliveryItemStateEnum::cases() as $case) {
            $stats['number_stock_delivery_items_state_'.$case->snake()] = (int) Arr::get($stateCounts, $case->value, 0);
        }

        $checkedItemsCount = (int) Arr::get($stateCounts, StockDeliveryItemStateEnum::CHECKED->value, 0);

        if ($stockDelivery->state === StockDeliveryStateEnum::RECEIVED && $checkedItemsCount === $items && $items > 0) {
            $stats['state']      = StockDeliveryStateEnum::CHECKED;
            $stats['checked_at'] = now();
        }

        $stockDelivery->update($stats);
    }

    private function getDeliveryDiscrepancy(StockDelivery $stockDelivery): array
    {
        $discrepancies = $stockDelivery->items()
            ->whereNotNull('checked_at')
            ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
            ->whereColumn('unit_quantity_checked', '!=', 'unit_quantity')
            ->with('organisation')
            ->get()
            ->map(fn (StockDeliveryItem $item) => $item->discrepancy());

        return [
            'under_delivered'        => $discrepancies->filter(fn ($discrepancy) => $discrepancy === StockDeliveryItemDiscrepancyEnum::UNDER)->count(),
            'over_delivered'         => $discrepancies->filter(fn ($discrepancy) => $discrepancy === StockDeliveryItemDiscrepancyEnum::OVER)->count(),
            'possible_unit_mismatch' => $discrepancies->filter(fn ($discrepancy) => $discrepancy === StockDeliveryItemDiscrepancyEnum::POSSIBLE_UNIT_MISMATCH)->count(),
        ];
    }

    private function getWeights(StockDelivery $stockDelivery): array
    {
        $lines = DB::table('stock_delivery_items as sdi')
            ->where('sdi.stock_delivery_id', $stockDelivery->id)
            ->whereNull('sdi.deleted_at')
            ->selectSub($this->tradeUnitWeightSubQuery('gross_weight'), 'gross_weight')
            ->selectSub($this->tradeUnitWeightSubQuery('net_weight'), 'net_weight');

        $totals = DB::query()
            ->fromSub($lines, 'line')
            ->selectRaw('sum(line.gross_weight) as gross_weight')
            ->selectRaw('sum(line.net_weight) as net_weight')
            ->first();

        return [
            'gross_weight' => $this->gramsToKilograms($totals->gross_weight),
            'net_weight'   => $this->gramsToKilograms($totals->net_weight),
        ];
    }

    private function tradeUnitWeightSubQuery(string $column): Builder
    {
        return DB::table('model_has_trade_units as mhtu')
            ->join('trade_units as tu', 'tu.id', '=', 'mhtu.trade_unit_id')
            ->join('org_stocks as pack', 'pack.id', '=', 'mhtu.model_id')
            ->whereColumn('mhtu.model_id', 'sdi.org_stock_id')
            ->where('mhtu.model_type', 'OrgStock')
            ->selectRaw("
                case
                    when count(*) = 0 or count(*) filter (where tu.$column is null) > 0 then null
                    else sum(tu.$column * mhtu.quantity) * sdi.unit_quantity / coalesce(nullif(max(pack.packed_in), 0), 1)
                end
            ");
    }

    private function gramsToKilograms(int|float|string|null $grams): ?float
    {
        return $grams === null ? null : round($grams / 1000, 1);
    }
}
