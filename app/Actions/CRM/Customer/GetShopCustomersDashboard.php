<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCustomersDashboard;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The shop dashboard's Customers tab. Reads only precomputed data: the snapshot that
 * ShopHydrateCustomersDashboard keeps in shop_crm_stats, and this month's registrations from the
 * shop's daily time series.
 */
class GetShopCustomersDashboard
{
    use AsObject;

    public function handle(Shop $shop, ?Carbon $today = null): array
    {
        $today    = ($today ?? now('UTC'))->copy()->startOfDay();
        $snapshot = $shop->crmStats?->customers_dashboard;

        if (!$snapshot) {
            ShopHydrateCustomersDashboard::dispatch($shop);

            return ['pending' => true];
        }

        $monthStart = $today->copy()->startOfMonth();
        $thisMonth  = $this->registrations($shop, $monthStart, $today);
        $lastYear   = $this->registrations($shop, $monthStart->copy()->subYear(), $today->copy()->subYear());

        $parameters = ['organisation' => $shop->organisation->slug, 'shop' => $shop->slug];

        return array_merge($snapshot, [
            'currency_code' => $shop->currency->code,
            'month_label'   => $monthStart->translatedFormat('F'),
            'hydrated_at'   => $shop->crmStats->customers_dashboard_hydrated_at,
            'this_month'    => [
                'registrations'                       => $thisMonth['registrations'],
                'registrations_last_year'             => $lastYear['registrations'],
                'registrations_with_orders'           => $thisMonth['with_orders'],
                'registrations_with_orders_last_year' => $lastYear['with_orders'],
            ],
            'routes'        => [
                'customers' => ['name' => 'grp.org.shops.show.crm.customers.index', 'parameters' => $parameters],
                'customer'  => ['name' => 'grp.org.shops.show.crm.customers.show', 'parameters' => $parameters],
            ],
        ]);
    }

    /**
     * @return array{registrations: int, with_orders: int}
     */
    private function registrations(Shop $shop, Carbon $from, Carbon $to): array
    {
        $totals = DB::table('shop_time_series_records')
            ->join('shop_time_series', 'shop_time_series.id', '=', 'shop_time_series_records.shop_time_series_id')
            ->where('shop_time_series.shop_id', $shop->id)
            ->where('shop_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->whereBetween('shop_time_series_records.period', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('coalesce(sum(registrations_with_orders), 0) as with_orders, coalesce(sum(registrations_without_orders), 0) as without_orders')
            ->first();

        return [
            'registrations' => (int) $totals->with_orders + (int) $totals->without_orders,
            'with_orders'   => (int) $totals->with_orders,
        ];
    }
}
