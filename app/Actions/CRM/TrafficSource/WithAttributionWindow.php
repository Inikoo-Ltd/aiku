<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * One definition of which revenue a marketing touch may claim, shared by every place that answers
 * that question: the dashboard, both stats hydrators, and the email performance panel. They used to
 * be separate copies, and a channel's campaigns could then legitimately out-earn the channel itself.
 *
 * The `marketing_attributable_invoices` SQL view mirrors this rule for the reporting views; keep the
 * two in step.
 */
trait WithAttributionWindow
{
    use WithTouchCausality;

    /** Kept identical in the `marketing_attributable_invoices` view. */
    public const ATTRIBUTABLE_DATE = "COALESCE((SELECT o.date FROM orders o WHERE o.id = invoices.order_id), invoices.date)";

    /**
     * Revenue is judged by when the customer *ordered*, not when the paperwork was raised. Invoicing
     * lags orders by a day or two, and a touch landing inside that lag was collecting orders that had
     * already been placed - 93% of all attributed revenue in prod, on the day this was found. The
     * invoice date is the fallback for invoices with no linked order.
     *
     * Rows with no recorded touch date are legacy and are left in rather than silently dropped, so
     * historic attribution does not vanish the day this ships.
     *
     * @param \Illuminate\Database\Query\JoinClause $join
     */
    protected function constrainToAttributionWindow($join, int $window, string $pivotAlias = 'p'): void
    {
        $this->constrainToTouchWindow($join, self::ATTRIBUTABLE_DATE, $window, $pivotAlias);
    }

    /**
     * The same rule, per customer: what one customer's invoices are worth to a channel, share-weighted
     * and clipped to the window, so a listing of a channel's customers adds up to the channel's own
     * revenue figure instead of to those customers' entire trading history.
     *
     * Correlated on `customers.id`, so it is used as a select on a query over `customers`.
     */
    protected function attributedRevenuePerCustomer(int $window, ?int $trafficSourceId = null, ?string $channelType = null): Builder
    {
        $query = DB::table('invoices')
            ->join('model_has_traffic_sources as p', function ($join) use ($window) {
                $join->on('p.model_id', '=', 'invoices.customer_id')
                    ->where('p.model_type', '=', 'Customer');

                $this->constrainToAttributionWindow($join, $window);
            })
            ->whereColumn('invoices.customer_id', 'customers.id')
            ->where('invoices.in_process', false)
            ->selectRaw('COALESCE(SUM(invoices.net_amount * p.share), 0)');

        if ($trafficSourceId) {
            return $query->where('p.traffic_source_id', $trafficSourceId);
        }

        return $query->join('traffic_sources as ts', 'ts.id', '=', 'p.traffic_source_id')
            ->where('ts.type', $channelType);
    }
}
