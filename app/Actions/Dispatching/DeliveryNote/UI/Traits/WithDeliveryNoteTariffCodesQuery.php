<?php

namespace App\Actions\Dispatching\DeliveryNote\UI\Traits;

use App\Models\Dispatching\DeliveryNote;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait WithDeliveryNoteTariffCodesQuery
{
    protected function getTariffCodesBaseQuery(DeliveryNote $deliveryNote): Builder
    {
        // ponytail: an org stock with several trade units would be counted once per tariff code row
        // (units and amount inflated). No such org stock exists, split the quantity if one ever does.
        $origin     = 'COALESCE(c.code, tu.country_of_origin)';
        $incomplete = "(tu.tariff_code IS NULL OR $origin IS NULL)";
        $tariffCode = "COALESCE(left(replace(tu.tariff_code, ' ', ''), 6) || tco.national_extension, tu.tariff_code)";
        $share      = $this->getTransactionShareSql();

        return DB::table('delivery_note_items as dni')
            ->leftJoin('model_has_trade_units as mhtu', function ($join) {
                $join->on('mhtu.model_id', '=', 'dni.org_stock_id')
                    ->where('mhtu.model_type', 'OrgStock');
            })
            ->leftJoin('trade_units as tu', 'tu.id', '=', 'mhtu.trade_unit_id')
            ->leftJoin('trade_unit_tariff_code_overrides as tco', function ($join) use ($deliveryNote) {
                $join->on('tco.trade_unit_id', '=', 'tu.id')
                    ->where('tco.organisation_id', $deliveryNote->organisation_id);
            })
            ->leftJoin('org_stocks as os', 'os.id', '=', 'dni.org_stock_id')
            ->leftJoin('countries as c', 'c.id', '=', 'tu.origin_country_id')
            ->leftJoin('tariff_codes as tc', 'tc.hs_code', '=', DB::raw('left(tu.tariff_code, 6)'))
            ->leftJoin('transactions as t', 't.id', '=', 'dni.transaction_id')
            ->leftJoinSub($this->getTransactionPartsQuery($deliveryNote), 'tp', 'tp.transaction_id', '=', 'dni.transaction_id')
            ->where('dni.delivery_note_id', $deliveryNote->id)
            ->groupBy(
                DB::raw("CASE WHEN $incomplete THEN NULL ELSE $tariffCode END"),
                DB::raw("CASE WHEN $incomplete THEN NULL ELSE $origin END")
            )
            ->select([
                DB::raw("CASE WHEN $incomplete THEN NULL ELSE $tariffCode END as tariff_code"),
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
                    'org_stock_slug', os.slug,
                    'trade_unit_slug', tu.slug,
                    'trade_unit_code', tu.code,
                    'trade_unit_name', tu.name,
                    'missing_tariff_code', tu.tariff_code IS NULL,
                    'missing_origin', $origin IS NULL
                )) FILTER (WHERE $incomplete) as offenders"),
                DB::raw('COALESCE(SUM(dni.quantity_required), 0) as units'),
                DB::raw('ROUND(COALESCE(SUM(tu.gross_weight * mhtu.quantity * dni.quantity_required), 0)::numeric / 1000, 3) as weight'),
                DB::raw("ROUND(COALESCE(SUM(t.net_amount * $share), 0)::numeric, 2) as amount"),
            ]);
    }

    /**
     * A product made of several parts (HELP-3131: roller + pouch) has one transaction but one delivery note
     * item per part, so the transaction amount is split between the parts. Per transaction the best basis
     * every part has is used: its own selling price, else supplier cost, else stock value, else equal shares.
     *
     * @return array<string, string>
     */
    protected function getPartValueSources(): array
    {
        return [
            'price' => 'sku_commercial_value',
            'cost'  => 'current_supplier_sku_cost',
            'value' => 'sku_value',
        ];
    }

    protected function getTransactionShareSql(): string
    {
        $cases = '';
        foreach ($this->getPartValueSources() as $key => $column) {
            $cases .= " WHEN tp.all_have_$key THEN dni.quantity_required * os.$column / tp.{$key}_sum";
        }

        return "CASE$cases ELSE 1.0 / COALESCE(tp.parts_count, 1) END";
    }

    protected function getTransactionPartsQuery(DeliveryNote $deliveryNote): Builder
    {
        $selects = ['x.transaction_id', DB::raw('COUNT(*) as parts_count')];
        foreach ($this->getPartValueSources() as $key => $column) {
            $selects[] = DB::raw("bool_and(COALESCE(xos.$column, 0) > 0) as all_have_$key");
            $selects[] = DB::raw("SUM(x.quantity_required * COALESCE(xos.$column, 0)) as {$key}_sum");
        }

        return DB::table('delivery_note_items as x')
            ->leftJoin('org_stocks as xos', 'xos.id', '=', 'x.org_stock_id')
            ->where('x.delivery_note_id', $deliveryNote->id)
            ->groupBy('x.transaction_id')
            ->select($selects);
    }
}
