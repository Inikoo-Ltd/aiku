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
use App\Actions\CRM\TrafficSource\GetAttributionWindow;
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
     *
     * Revenue is what the channel actually earned, not what its customers have ever spent: only
     * invoices raised after a touch, and within the shop's attribution window, are counted. Before
     * this, a customer registered in 2022 who clicked a newsletter yesterday handed that newsletter
     * four years of prior trade, and the listing reported it as marketing performance.
     */
    public function handle(TrafficSource $trafficSource): void
    {
        $counts = DB::table('model_has_traffic_sources')
            ->join('customer_stats', 'customer_stats.customer_id', '=', 'model_has_traffic_sources.model_id')
            ->where('model_has_traffic_sources.traffic_source_id', $trafficSource->id)
            ->where('model_has_traffic_sources.model_type', 'Customer')
            ->select(
                DB::raw('COALESCE(SUM(model_has_traffic_sources.share), 0) as customers'),
                DB::raw('COALESCE(SUM(customer_stats.number_orders_state_dispatched * model_has_traffic_sources.share), 0) as purchases'),
            )
            ->first();

        $window = GetAttributionWindow::run($trafficSource->shop);

        $revenue = DB::table('invoices')
            ->join('model_has_traffic_sources as p', function ($join) use ($window) {
                $join->on('p.model_id', '=', 'invoices.customer_id')
                    ->where('p.model_type', '=', 'Customer');

                $join->where(function ($q) use ($window) {
                    $q->whereNull('p.first_touch_at')
                        ->orWhere(function ($inWindow) use ($window) {
                            $inWindow->whereColumn('invoices.date', '>=', 'p.first_touch_at');

                            if ($window > 0) {
                                $inWindow->whereRaw(
                                    "invoices.date <= p.last_touch_at + (? || ' days')::interval",
                                    [$window]
                                );
                            }
                        });
                });
            })
            ->where('p.traffic_source_id', $trafficSource->id)
            ->where('invoices.in_process', false)
            ->select(
                DB::raw('COALESCE(SUM(invoices.net_amount * p.share), 0) as revenue'),
                DB::raw('COALESCE(SUM(invoices.org_net_amount * p.share), 0) as org_revenue'),
            )
            ->first();

        $totals = (object) [
            'customers'   => $counts->customers,
            'purchases'   => $counts->purchases,
            'revenue'     => $revenue->revenue,
            'org_revenue' => $revenue->org_revenue,
        ];

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
