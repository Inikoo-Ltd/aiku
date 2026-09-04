<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
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
    /**
     * Trade that never went through the website: phone, showroom, marketplaces. It sits in the shop
     * total management knows by heart, so hiding it would make the table look short; but no channel
     * can claim it and no visit precedes it, so it is listed on its own rather than left in Direct.
     * Orders and invoices with no sales channel are website trade, which is where the bulk of the
     * older web orders still sit.
     *
     * @param array<int, int> $shopIds
     *
     * @return array<int, array{name: string, revenue: float, orders: float}>
     */
    protected function outOfScopeSalesChannels(array $shopIds, ?Carbon $from, ?Carbon $to, string $revenueColumn): array
    {
        $isOutOfScope = fn ($query) => $query
            ->whereNotNull('sales_channel_id')
            ->where('sc.type', '!=', SalesChannelTypeEnum::WEBSITE->value);

        $revenue = DB::table('invoices')
            ->join('sales_channels as sc', 'sc.id', '=', 'invoices.sales_channel_id')
            ->whereIn('invoices.shop_id', $shopIds)
            ->where('in_process', false)
            ->tap($isOutOfScope)
            ->when($from, fn ($query) => $query->where('invoices.date', '>=', $from))
            ->when($to, fn ($query) => $query->where('invoices.date', '<=', $to))
            ->groupBy('sc.name')
            ->select('sc.name', DB::raw("SUM({$revenueColumn}) as total"))
            ->pluck('total', 'name');

        $orders = DB::table('orders')
            ->join('sales_channels as sc', 'sc.id', '=', 'orders.sales_channel_id')
            ->whereIn('orders.shop_id', $shopIds)
            ->whereNotIn('state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])
            ->whereNull('orders.deleted_at')
            ->tap($isOutOfScope)
            ->when($from, fn ($query) => $query->where('orders.date', '>=', $from))
            ->when($to, fn ($query) => $query->where('orders.date', '<=', $to))
            ->groupBy('sc.name')
            ->select('sc.name', DB::raw('COUNT(*) as total'))
            ->pluck('total', 'name');

        return collect(array_unique(array_merge($revenue->keys()->all(), $orders->keys()->all())))
            ->map(fn (string $name) => [
                'name'    => trim($name),
                'revenue' => round((float) ($revenue[$name] ?? 0), 2),
                'orders'  => (float) ($orders[$name] ?? 0),
            ])
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }
}
