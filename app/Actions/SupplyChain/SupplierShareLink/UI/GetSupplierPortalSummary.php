<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Procurement\OrgAgent\UI\GetAgentSalesProducts;
use App\Actions\Procurement\OrgAgent\UI\ShowAgentSales;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Helpers\Country;
use App\Models\Helpers\Currency;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The supplier's sales in the last twelve whole months at its own prices, against the twelve before, and where that
 * puts it among the suppliers of its country, behind an agent or not. Only its own figures and the size of the group
 * are given, never another supplier.
 */
class GetSupplierPortalSummary
{
    use AsObject;

    /**
     * @return array<string, mixed>
     */
    public function handle(Supplier $supplier): array
    {
        $to           = now()->subMonthNoOverflow()->endOfMonth();
        $from         = $to->copy()->subMonthsNoOverflow(11)->startOfMonth();
        $previousTo   = $from->copy()->subDay();
        $previousFrom = $from->copy()->subYear();
        $countryCode  = $supplier->address?->country_code;

        $sales = $countryCode ? $this->salesInCountry($supplier, $countryCode, [$from->format('Y-m'), $to->format('Y-m'), $previousFrom->format('Y-m'), $previousTo->format('Y-m')]) : collect();
        $own   = $sales->firstWhere('supplier_id', $supplier->id);

        $current  = round((float) ($own->current ?? 0), 2);
        $previous = round((float) ($own->previous ?? 0), 2);
        $ranked   = $sales->filter(fn ($row) => $row->current_grp > 0);

        return [
            'currency'      => $supplier->currency?->code,
            'from'          => $from->toDateString(),
            'to'            => $to->toDateString(),
            'previous_from' => $previousFrom->toDateString(),
            'previous_to'   => $previousTo->toDateString(),
            'as_of'         => now()->toDateString(),
            'sales'         => $current,
            'previous'      => $previous,
            'change'        => $previous > 0 ? round(($current / $previous - 1) * 100, 1) : null,
            'country'       => $countryCode ? ['code' => $countryCode, 'name' => Country::where('code', $countryCode)->value('name') ?? $countryCode] : null,
            'ranking'       => ($own->current_grp ?? 0) > 0 ? [
                'position' => 1 + $ranked->where('current_grp', '>', $own->current_grp)->count(),
                'of'       => $ranked->count(),
            ] : null,
        ];
    }

    /**
     * What each supplier of the country sold, in its own currency and in the group's to compare them.
     *
     * @param  array{0: string, 1: string, 2: string, 3: string}  $periods
     */
    private function salesInCountry(Supplier $supplier, string $countryCode, array $periods): Collection
    {
        [$from, $to, $previousFrom, $previousTo] = $periods;
        $sold = '(r.cogs_org_currency / nullif(os.sku_value, 0)) * '.ShowAgentSales::EXTERNAL_SHARE.' * prices.price';

        $prices = DB::table('org_stock_has_org_supplier_products as link')
            ->join('org_supplier_products as osp', 'osp.id', 'link.org_supplier_product_id')
            ->join('supplier_products as sp', 'sp.id', 'osp.supplier_product_id')
            ->join('suppliers as sup', 'sup.id', 'sp.supplier_id')
            ->join('addresses as address', 'address.id', 'sup.address_id')
            ->where('link.status', true)
            ->where('sup.group_id', $supplier->group_id)
            ->where('sup.status', true)
            ->whereNull('sup.deleted_at')
            ->where('address.country_code', $countryCode)
            ->groupBy('sp.supplier_id', 'link.org_stock_id')
            ->selectRaw('sp.supplier_id, link.org_stock_id, min(sp.cost * coalesce(sp.units_per_pack, 1)) / '.GetAgentSalesProducts::suppliersOfSql('link.org_stock_id').' as price');

        $rows = DB::query()->fromSub($prices, 'prices')
            ->join('suppliers as sup', 'sup.id', 'prices.supplier_id')
            ->join('org_stocks as os', 'os.id', 'prices.org_stock_id')
            ->join('org_stock_time_series as ts', fn ($join) => $join->on('ts.org_stock_id', 'prices.org_stock_id')->where('ts.frequency', TimeSeriesFrequencyEnum::MONTHLY->value))
            ->join('org_stock_time_series_records as r', 'r.org_stock_time_series_id', 'ts.id')
            ->whereBetween('r.period', [$previousFrom, $to])
            ->groupBy('prices.supplier_id', 'sup.currency_id')
            ->selectRaw(
                "prices.supplier_id, sup.currency_id,
                sum(case when r.period between ? and ? then $sold end) as current,
                sum(case when r.period between ? and ? then $sold end) as previous",
                [$from, $to, $previousFrom, $previousTo]
            )
            ->get();

        $groupCurrency = $supplier->group->currency;
        $toGroup       = Currency::whereIn('id', $rows->pluck('currency_id')->unique())->get()
            ->mapWithKeys(fn (Currency $currency) => [$currency->id => (float) GetCurrencyExchange::run($currency, $groupCurrency)]);

        return $rows->map(function ($row) use ($toGroup) {
            $row->current_grp = (float) $row->current * ($toGroup[$row->currency_id] ?? 0);

            return $row;
        });
    }
}
