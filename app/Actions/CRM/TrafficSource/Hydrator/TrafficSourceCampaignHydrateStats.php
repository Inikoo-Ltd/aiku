<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource\Hydrator;

use App\Actions\CRM\TrafficSource\GetAttributionWindow;
use App\Actions\CRM\TrafficSource\WithAttributionWindow;
use App\Models\CRM\TrafficSourceCampaign;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class TrafficSourceCampaignHydrateStats implements ShouldBeUnique
{
    use AsAction;
    use WithAttributionWindow;

    public int $jobUniqueFor = 3600;

    /* Dashboard statistics: never allowed to compete with order processing. */
    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(TrafficSourceCampaign $campaign): string
    {
        return (string) $campaign->id;
    }

    /**
     * Rolls a campaign's own customers, orders, revenue and spend into its stats row, weighted by the
     * attribution shares exactly as the traffic-source hydrator does. Summing every campaign of a
     * source therefore never exceeds that source's own totals: a customer split between two campaigns
     * contributes half to each.
     *
     * A campaign that has spend but no attributed customer still gets a row, so money spent with
     * nothing to show for it is visible rather than absent.
     */
    public function handle(TrafficSourceCampaign $campaign): void
    {
        $attribution = DB::table('model_has_traffic_sources')
            ->join('customer_stats', 'customer_stats.customer_id', '=', 'model_has_traffic_sources.model_id')
            ->where('model_has_traffic_sources.traffic_source_campaign_id', $campaign->id)
            ->where('model_has_traffic_sources.model_type', 'Customer')
            ->select(
                DB::raw('COALESCE(SUM(model_has_traffic_sources.share), 0) as customers'),
                DB::raw('COALESCE(SUM(customer_stats.number_orders_state_dispatched * model_has_traffic_sources.share), 0) as purchases'),
            )
            ->first();

        $window  = GetAttributionWindow::run($campaign->trafficSource->shop);
        $revenue = DB::table('invoices')
            ->join('model_has_traffic_sources as p', function ($join) use ($window) {
                $join->on('p.model_id', '=', 'invoices.customer_id')
                    ->where('p.model_type', '=', 'Customer');

                $this->constrainToAttributionWindow($join, $window);
            })
            ->where('p.traffic_source_campaign_id', $campaign->id)
            ->where('invoices.in_process', false)
            ->select(
                DB::raw('COALESCE(SUM(invoices.net_amount * p.share), 0) as revenue'),
                DB::raw('COALESCE(SUM(invoices.org_net_amount * p.share), 0) as org_revenue'),
            )
            ->first();

        $cost = DB::table('traffic_source_costs')
            ->where('traffic_source_campaign_id', $campaign->id)
            ->select(
                DB::raw('COALESCE(SUM(amount), 0) as total'),
                DB::raw('COALESCE(SUM(org_amount), 0) as org_total'),
            )
            ->first();

        $campaign->stats()->updateOrCreate(
            ['traffic_source_campaign_id' => $campaign->id],
            [
                'number_customers'           => $attribution->customers,
                'number_customer_purchases'  => $attribution->purchases,
                'total_customer_revenue'     => $revenue->revenue,
                'org_total_customer_revenue' => $revenue->org_revenue,
                'total_cost'                 => $cost->total,
                'org_total_cost'             => $cost->org_total,
            ]
        );
    }
}
