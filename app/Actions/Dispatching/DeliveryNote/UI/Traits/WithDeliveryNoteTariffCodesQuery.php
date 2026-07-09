<?php

namespace App\Actions\Dispatching\DeliveryNote\UI\Traits;

use App\Models\Dispatching\DeliveryNote;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait WithDeliveryNoteTariffCodesQuery
{
    protected function getTariffCodesBaseQuery(DeliveryNote $deliveryNote): Builder
    {
        return DB::table('delivery_note_items as dni')
            ->join('model_has_trade_units as mhtu', function ($join) {
                $join->on('mhtu.model_id', '=', 'dni.org_stock_id')
                    ->where('mhtu.model_type', 'OrgStock');
            })
            ->join('trade_units as tu', 'tu.id', '=', 'mhtu.trade_unit_id')
            ->leftJoin('org_stocks as os', 'os.id', '=', 'dni.org_stock_id')
            ->leftJoin('countries as c', 'c.id', '=', 'tu.origin_country_id')
            ->leftJoin('tariff_codes as tc', 'tc.hs_code', '=', DB::raw('left(tu.tariff_code, 6)'))
            ->leftJoin('transactions as t', 't.id', '=', 'dni.transaction_id')
            ->where('dni.delivery_note_id', $deliveryNote->id)
            ->whereNotNull('tu.tariff_code')
            ->groupBy('tu.tariff_code', DB::raw('COALESCE(c.code, tu.country_of_origin)'))
            ->select([
                'tu.tariff_code',
                DB::raw('MAX(tc.description) as description'),
                DB::raw('COALESCE(c.code, tu.country_of_origin) as origin'),
                DB::raw("bool_or(tu.un_number IS NOT NULL AND tu.un_number <> 'None') as dg"),
                DB::raw("string_agg(DISTINCT tu.un_number, ', ') FILTER (WHERE tu.un_number IS NOT NULL AND tu.un_number <> 'None') as un_numbers"),
                DB::raw("string_agg(DISTINCT os.code, ', ' ORDER BY os.code) FILTER (WHERE os.code IS NOT NULL) as parts"),
                DB::raw('COUNT(DISTINCT os.code) as num_parts'),
                DB::raw('COALESCE(SUM(dni.quantity_required), 0) as units'),
                DB::raw('ROUND(COALESCE(SUM(dni.weight), 0)::numeric, 3) as weight'),
                DB::raw('ROUND(COALESCE(SUM(t.net_amount), 0)::numeric, 2) as amount'),
            ]);
    }
}
