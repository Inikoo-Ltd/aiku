<?php

namespace App\Actions\GoodsIn\StockDelivery\Traits;

use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait WithStockDeliveryWeightAndVolume
{
    public function getStockDeliveryWeightAndVolume(StockDelivery $stockDelivery): array
    {
        $lines = DB::table('stock_delivery_items as sdi')
            ->leftJoin('supplier_products as sp', 'sp.id', '=', 'sdi.supplier_product_id')
            ->where('sdi.stock_delivery_id', $stockDelivery->id)
            ->whereNull('sdi.deleted_at')
            ->selectRaw("sdi.state = '".StockDeliveryItemStateEnum::CANCELLED->value."' as is_cancelled")
            ->selectSub($this->tradeUnitWeightSubQuery('gross_weight'), 'gross_weight')
            ->selectSub($this->tradeUnitWeightSubQuery('net_weight'), 'net_weight')
            ->selectRaw('sp.cbm * sdi.unit_quantity / nullif(sp.units_per_carton, 0) as volume');

        $totals = DB::query()
            ->fromSub($lines, 'line')
            ->selectRaw('sum(line.gross_weight) filter (where not line.is_cancelled) as gross_weight')
            ->selectRaw('sum(line.net_weight) filter (where not line.is_cancelled) as net_weight')
            ->selectRaw('sum(line.volume) filter (where not line.is_cancelled) as volume')
            ->selectRaw('count(*) filter (where line.gross_weight is null and not line.is_cancelled) as unknown_weight_lines')
            ->selectRaw('count(*) filter (where line.volume is null and not line.is_cancelled) as unknown_volume_lines')
            ->selectRaw('count(*) as total_lines')
            ->selectRaw('count(*) filter (where not line.is_cancelled) as active_lines')
            ->first();

        if ($totals->total_lines > 0 && (int) $totals->active_lines === 0) {
            return [
                'gross_weight'      => 0.0,
                'net_weight'        => 0.0,
                'volume'            => 0.0,
                'is_weight_partial' => false,
                'is_volume_partial' => false,
            ];
        }

        return [
            'gross_weight'      => $this->gramsToKilograms($totals->gross_weight),
            'net_weight'        => $this->gramsToKilograms($totals->net_weight),
            'volume'            => $totals->volume === null ? null : round($totals->volume, 2),
            'is_weight_partial' => $totals->unknown_weight_lines > 0,
            'is_volume_partial' => $totals->unknown_volume_lines > 0,
        ];
    }

    private function gramsToKilograms(int|float|string|null $grams): ?float
    {
        return $grams === null ? null : round($grams / 1000, 1);
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
}
