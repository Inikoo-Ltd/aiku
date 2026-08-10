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
use App\Actions\CRM\TrafficSource\WithAttributionWindow;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\CRM\TrafficSource;

class TrafficSourceHydrateCustomers implements ShouldBeUnique
{
    use AsAction;
    use WithAttributionWindow;

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
     * Every figure here answers "what did this channel bring in", not "what have its customers ever
     * done": a registration, an order or an invoice counts only if it happened after the touch and
     * inside the shop's attribution window. Before this, a customer registered in 2022 who clicked a
     * newsletter yesterday handed that newsletter four years of prior trade and their registration
     * too, and the listing reported it as marketing performance.
     */
    public function handle(TrafficSource $trafficSource): void
    {
        $window = GetAttributionWindow::run($trafficSource->shop);

        $customers = DB::table('model_has_traffic_sources as p')
            ->join('customers', 'customers.id', '=', 'p.model_id')
            ->where('p.traffic_source_id', $trafficSource->id)
            ->where('p.model_type', 'Customer')
            ->tap(fn ($query) => $this->constrainToTouchWindow($query, 'customers.created_at', $window))
            ->sum('p.share');

        /* Counted from the orders themselves rather than from customer_stats: the rollup carries no
           dates, so there is no way to tell an order the channel earned from one placed years before
           the touch existed. */
        $purchases = DB::table('model_has_traffic_sources as p')
            ->join('orders', 'orders.customer_id', '=', 'p.model_id')
            ->where('p.traffic_source_id', $trafficSource->id)
            ->where('p.model_type', 'Customer')
            ->whereNotIn('orders.state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])
            ->whereNull('orders.deleted_at')
            ->tap(fn ($query) => $this->constrainToTouchWindow($query, 'orders.date', $window))
            ->sum('p.share');

        $revenue = DB::table('invoices')
            ->join('model_has_traffic_sources as p', function ($join) use ($window) {
                $join->on('p.model_id', '=', 'invoices.customer_id')
                    ->where('p.model_type', '=', 'Customer');

                $this->constrainToAttributionWindow($join, $window);
            })
            ->where('p.traffic_source_id', $trafficSource->id)
            ->where('invoices.in_process', false)
            ->select(
                DB::raw('COALESCE(SUM(invoices.net_amount * p.share), 0) as revenue'),
                DB::raw('COALESCE(SUM(invoices.org_net_amount * p.share), 0) as org_revenue'),
            )
            ->first();

        $totals = (object) [
            'customers'   => $customers,
            'purchases'   => $purchases,
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
