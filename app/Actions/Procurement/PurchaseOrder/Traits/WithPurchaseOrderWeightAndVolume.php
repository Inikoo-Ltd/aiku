<?php

namespace App\Actions\Procurement\PurchaseOrder\Traits;

use App\Models\Procurement\PurchaseOrder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait WithPurchaseOrderWeightAndVolume
{
    /**
     * @return array{gross_weight: ?float, net_weight: ?float, volume: ?float, is_weight_partial: bool, is_volume_partial: bool}
     */
    public function getPurchaseOrderWeightAndVolume(PurchaseOrder $purchaseOrder): array
    {
        $lines = DB::table('purchase_order_transactions as pot')
            ->leftJoin('supplier_products as sp', 'sp.id', '=', 'pot.supplier_product_id')
            ->where('pot.purchase_order_id', $purchaseOrder->id)
            ->selectSub($this->tradeUnitWeightSubQuery('gross_weight'), 'gross_weight')
            ->selectSub($this->tradeUnitWeightSubQuery('net_weight'), 'net_weight')
            ->selectRaw('sp.cbm * pot.quantity_ordered / nullif(sp.units_per_carton, 0) as volume');

        $totals = DB::query()
            ->fromSub($lines, 'line')
            ->selectRaw('sum(line.gross_weight) as gross_weight')
            ->selectRaw('sum(line.net_weight) as net_weight')
            ->selectRaw('sum(line.volume) as volume')
            ->selectRaw('count(*) filter (where line.gross_weight is null) as unknown_weight_lines')
            ->selectRaw('count(*) filter (where line.volume is null) as unknown_volume_lines')
            ->first();

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
            ->whereColumn('mhtu.model_id', 'pot.org_stock_id')
            ->where('mhtu.model_type', 'OrgStock')
            ->selectRaw("
                case
                    when count(*) = 0 or count(*) filter (where tu.$column is null) > 0 then null
                    else sum(tu.$column * mhtu.quantity) * pot.quantity_ordered
                end
            ");
    }
}
