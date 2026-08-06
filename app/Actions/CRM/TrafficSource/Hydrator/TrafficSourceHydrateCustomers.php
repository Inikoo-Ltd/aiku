<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 19-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\TrafficSource\Hydrator;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\CRM\TrafficSource;

class TrafficSourceHydrateCustomers implements ShouldBeUnique
{
    use AsAction;

    /* Fired from every registration, order cancel and email-click recalculation; the numbers it
       refreshes are dashboard statistics, so it must never compete with order processing. */
    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(TrafficSource $trafficSource): string
    {
        return $trafficSource->id;
    }

    /**
     * Everything is weighted by the attribution share, so a customer split between channels is split
     * here too: half a customer, half their orders, half their revenue to each. Summing any of these
     * figures across every traffic source of a shop therefore reproduces the shop's real totals —
     * channels share the credit, they never each claim the whole of it.
     */
    public function handle(TrafficSource $trafficSource): void
    {
        $totals = DB::table('model_has_traffic_sources')
            ->join('customer_stats', 'customer_stats.customer_id', '=', 'model_has_traffic_sources.model_id')
            ->where('model_has_traffic_sources.traffic_source_id', $trafficSource->id)
            ->where('model_has_traffic_sources.model_type', 'Customer')
            ->select(
                DB::raw('COALESCE(SUM(model_has_traffic_sources.share), 0) as customers'),
                DB::raw('COALESCE(SUM(customer_stats.number_orders_state_dispatched * model_has_traffic_sources.share), 0) as purchases'),
                DB::raw('COALESCE(SUM(customer_stats.sales_all * model_has_traffic_sources.share), 0) as revenue'),
                DB::raw('COALESCE(SUM(customer_stats.sales_org_currency_all * model_has_traffic_sources.share), 0) as org_revenue'),
            )
            ->first();

        $trafficSource->stats()->updateOrCreate(
            ['traffic_source_id' => $trafficSource->id],
            [
                'number_customers'           => $totals->customers,
                'number_customer_purchases'  => $totals->purchases,
                'total_customer_revenue'     => $totals->revenue,
                'org_total_customer_revenue' => $totals->org_revenue,
            ]
        );
    }
}
