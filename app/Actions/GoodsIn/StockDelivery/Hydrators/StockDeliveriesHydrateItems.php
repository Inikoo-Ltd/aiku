<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 00:53:14 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery\Hydrators;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class StockDeliveriesHydrateItems implements ShouldBeUnique
{
    use AsAction;

    public function handle(StockDelivery $stockDelivery): void
    {
        $weights = $this->getWeights($stockDelivery);

        $stats = [
            'number_stock_delivery_items' => $stockDelivery->items()->count(),
            'gross_weight'                => $weights['gross_weight'],
            'net_weight'                  => $weights['net_weight'],
        ];

        $checkedItemsCount = $stockDelivery->items()->where('state', StockDeliveryItemStateEnum::CHECKED)->count();
        $items             = $stockDelivery->items()->count();

        if (($checkedItemsCount === $items) && ($items > 0)) {
            $stats['state']                              = StockDeliveryStateEnum::CHECKED;
            $stats['checked_at']                         = now();
            $stats[$stockDelivery->state->value . '_at'] = null;
        }

        $stockDelivery->update($stats);
    }

    /**
     * @return array{gross_weight: ?float, net_weight: ?float}
     */
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
            ->whereColumn('mhtu.model_id', 'sdi.org_stock_id')
            ->where('mhtu.model_type', 'OrgStock')
            ->selectRaw("
                case
                    when count(*) = 0 or count(*) filter (where tu.$column is null) > 0 then null
                    else sum(tu.$column * mhtu.quantity) * sdi.unit_quantity
                end
            ");
    }

    private function gramsToKilograms(int|float|string|null $grams): ?float
    {
        return $grams === null ? null : round($grams / 1000, 1);
    }
}
