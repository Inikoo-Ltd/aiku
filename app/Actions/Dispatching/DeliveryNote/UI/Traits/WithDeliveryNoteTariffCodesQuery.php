<?php

namespace App\Actions\Dispatching\DeliveryNote\UI\Traits;

use App\Models\Dispatching\DeliveryNote;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait WithDeliveryNoteTariffCodesQuery
{
    protected function getTariffCodesBaseQuery(DeliveryNote $deliveryNote): Builder
    {
        $origin     = 'COALESCE(c.code, tu.country_of_origin)';
        $incomplete = "(tu.tariff_code IS NULL OR $origin IS NULL)";

        return DB::table('delivery_note_items as dni')
            ->leftJoin('model_has_trade_units as mhtu', function ($join) {
                $join->on('mhtu.model_id', '=', 'dni.org_stock_id')
                    ->where('mhtu.model_type', 'OrgStock');
            })
            ->leftJoin('trade_units as tu', 'tu.id', '=', 'mhtu.trade_unit_id')
            ->leftJoin('org_stocks as os', 'os.id', '=', 'dni.org_stock_id')
            ->leftJoin('countries as c', 'c.id', '=', 'tu.origin_country_id')
            ->leftJoin('tariff_codes as tc', 'tc.hs_code', '=', DB::raw('left(tu.tariff_code, 6)'))
            ->leftJoin('transactions as t', 't.id', '=', 'dni.transaction_id')
            ->where('dni.delivery_note_id', $deliveryNote->id)
            ->groupBy(
                DB::raw("CASE WHEN $incomplete THEN NULL ELSE tu.tariff_code END"),
                DB::raw("CASE WHEN $incomplete THEN NULL ELSE $origin END")
            )
            ->select([
                DB::raw("CASE WHEN $incomplete THEN NULL ELSE tu.tariff_code END as tariff_code"),
                DB::raw("bool_or($incomplete) as is_incomplete"),
                DB::raw("MAX(tc.description) FILTER (WHERE NOT $incomplete) as description"),
                DB::raw("CASE WHEN $incomplete THEN NULL ELSE $origin END as origin"),
                DB::raw('MAX(c.name) as origin_name'),
                DB::raw("bool_or(tu.un_number IS NOT NULL AND tu.un_number <> 'None') as dg"),
                DB::raw("string_agg(DISTINCT tu.un_number, ', ') FILTER (WHERE tu.un_number IS NOT NULL AND tu.un_number <> 'None') as un_numbers"),
                DB::raw("string_agg(DISTINCT os.code, ', ' ORDER BY os.code) FILTER (WHERE os.code IS NOT NULL) as parts"),
                DB::raw('COUNT(DISTINCT os.code) as num_parts'),
                DB::raw("jsonb_agg(DISTINCT jsonb_build_object(
                    'part', os.code,
                    'trade_unit_slug', tu.slug,
                    'trade_unit_code', tu.code,
                    'trade_unit_name', tu.name,
                    'missing_tariff_code', tu.tariff_code IS NULL,
                    'missing_origin', $origin IS NULL
                )) FILTER (WHERE $incomplete) as offenders"),
                DB::raw('COALESCE(SUM(dni.quantity_required), 0) as units'),
                DB::raw('ROUND(COALESCE(SUM(tu.gross_weight * mhtu.quantity * dni.quantity_required), 0)::numeric / 1000, 3) as weight'),
                DB::raw('ROUND(COALESCE(SUM(t.net_amount), 0)::numeric, 2) as amount'),
            ]);
    }
}
