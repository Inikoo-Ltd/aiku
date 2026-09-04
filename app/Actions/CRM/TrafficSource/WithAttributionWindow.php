<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
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
    public const ATTRIBUTABLE_DATE = "COALESCE((SELECT COALESCE(o.submitted_at, o.date) FROM orders o WHERE o.id = invoices.order_id), invoices.date)";

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
     * @return array<int, array{kind: 'partners'|'marketplaces'|'non_web', name: string, revenue: float, orders: float}>
     */
    protected function outOfScopeSalesChannels(array $shopIds, ?Carbon $from, ?Carbon $to, string $revenueColumn): array
    {
        /* Three kinds of trade the website did not bring in, each its own block on the page. Partners
           are group companies buying from each other, one line per sister company whatever channel
           the order was keyed under, so Phone stops hiding them. `as_organisation_id` is the flag: a
           customer that is one of our own organisations, stamped onto every order and invoice at
           creation. Marketplaces and the rest split by the sales channel's type. */
        $kindSql = fn (string $table) => "CASE WHEN {$table}.as_organisation_id IS NOT NULL THEN 'partners'"
            ." WHEN sc.type = '".SalesChannelTypeEnum::MARKETPLACE->value."' THEN 'marketplaces' ELSE 'non_web' END";
        $nameSql = fn (string $table) => "CASE WHEN {$table}.as_organisation_id IS NOT NULL THEN c.name ELSE sc.name END";
        $key     = fn ($row) => $row->kind.'|'.$row->name;

        $isOutOfScope = fn (string $table) => fn ($query) => $query->where(fn ($scope) => $scope
            ->where(fn ($channel) => $channel
                ->whereNotNull($table.'.sales_channel_id')
                ->where('sc.type', '!=', SalesChannelTypeEnum::WEBSITE->value))
            ->orWhereNotNull($table.'.as_organisation_id'));

        $revenue = DB::table('invoices')
            ->leftJoin('sales_channels as sc', 'sc.id', '=', 'invoices.sales_channel_id')
            ->join('customers as c', 'c.id', '=', 'invoices.customer_id')
            ->whereIn('invoices.shop_id', $shopIds)
            ->where('invoices.in_process', false)
            ->tap($isOutOfScope('invoices'))
            ->when($from, fn ($query) => $query->where('invoices.date', '>=', $from))
            ->when($to, fn ($query) => $query->where('invoices.date', '<=', $to))
            ->groupBy(DB::raw($kindSql('invoices')), DB::raw($nameSql('invoices')))
            ->select(DB::raw($kindSql('invoices').' as kind'), DB::raw($nameSql('invoices').' as name'), DB::raw("SUM({$revenueColumn}) as total"))
            ->get()
            ->keyBy($key);

        $orders = DB::table('orders')
            ->leftJoin('sales_channels as sc', 'sc.id', '=', 'orders.sales_channel_id')
            ->join('customers as c', 'c.id', '=', 'orders.customer_id')
            ->whereIn('orders.shop_id', $shopIds)
            ->whereNotIn('orders.state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])
            ->whereNull('orders.deleted_at')
            ->tap($isOutOfScope('orders'))
            ->when($from, fn ($query) => $query->whereRaw(self::ORDER_PLACED_AT.' >= ?', [$from]))
            ->when($to, fn ($query) => $query->whereRaw(self::ORDER_PLACED_AT.' <= ?', [$to]))
            ->groupBy(DB::raw($kindSql('orders')), DB::raw($nameSql('orders')))
            ->select(DB::raw($kindSql('orders').' as kind'), DB::raw($nameSql('orders').' as name'), DB::raw('COUNT(*) as total'))
            ->get()
            ->keyBy($key);

        return $revenue->keys()->merge($orders->keys())->unique()
            ->map(fn (string $key) => [
                'kind'    => explode('|', $key, 2)[0],
                'name'    => trim(explode('|', $key, 2)[1]),
                'revenue' => round((float) ($revenue[$key]->total ?? 0), 2),
                'orders'  => (float) ($orders[$key]->total ?? 0),
            ])
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }
    /**
     * The slice of Direct that is only Direct because we were not measuring yet: customers who
     * registered before attribution started and have had no recorded touch before their order. Their
     * origin is unknowable, not absent, and as the history grows past the window this slice moves
     * into the channels on its own. Shown so nobody reads it as proof that marketing does nothing.
     *
     * @param array<int, int> $shopIds
     *
     * @return array{revenue: float, orders: float}
     */
    protected function directBeforeTracking(array $shopIds, ?Carbon $from, ?Carbon $to, string $revenueColumn, int $window): array
    {
        $startedAt = GetAttributionStartedAt::run();

        if (!$startedAt) {
            return ['revenue' => 0.0, 'orders' => 0.0, 'reliable_from' => null];
        }

        $isWebsite = fn (string $table) => fn ($query) => $query->where(fn ($channel) => $channel
            ->whereNull($table.'.sales_channel_id')
            ->orWhere('sc.type', SalesChannelTypeEnum::WEBSITE->value));

        $revenue = DB::table('invoices')
            ->join('customers as c', 'c.id', '=', 'invoices.customer_id')
            ->leftJoin('sales_channels as sc', 'sc.id', '=', 'invoices.sales_channel_id')
            ->whereIn('invoices.shop_id', $shopIds)
            ->where('invoices.in_process', false)
            ->tap($isWebsite('invoices'))
            ->whereNull('invoices.as_organisation_id')
            ->where('c.created_at', '<', $startedAt)
            ->whereNotExists(fn ($touch) => $touch->select(DB::raw(1))->from('model_has_traffic_sources as p')
                ->whereColumn('p.model_id', 'invoices.customer_id')->where('p.model_type', 'Customer')
                ->whereRaw('p.first_touch_at <= '.self::ATTRIBUTABLE_DATE))
            ->when($from, fn ($query) => $query->where('invoices.date', '>=', $from))
            ->when($to, fn ($query) => $query->where('invoices.date', '<=', $to))
            ->sum($revenueColumn);

        $orders = DB::table('orders')
            ->join('customers as c', 'c.id', '=', 'orders.customer_id')
            ->leftJoin('sales_channels as sc', 'sc.id', '=', 'orders.sales_channel_id')
            ->whereIn('orders.shop_id', $shopIds)
            ->whereNotIn('orders.state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])
            ->whereNull('orders.deleted_at')
            ->tap($isWebsite('orders'))
            ->whereNull('orders.as_organisation_id')
            ->where('c.created_at', '<', $startedAt)
            ->whereNotExists(fn ($touch) => $touch->select(DB::raw(1))->from('model_has_traffic_sources as p')
                ->whereColumn('p.model_id', 'orders.customer_id')->where('p.model_type', 'Customer')
                ->whereRaw('p.first_touch_at <= '.self::ORDER_PLACED_AT))
            ->when($from, fn ($query) => $query->whereRaw(self::ORDER_PLACED_AT.' >= ?', [$from]))
            ->when($to, fn ($query) => $query->whereRaw(self::ORDER_PLACED_AT.' <= ?', [$to]))
            ->count();

        /* The day the recorded history is as long as the attribution window: from then on, a customer
           with no touch is genuinely direct rather than unmeasured. */
        return [
            'revenue'       => round((float) $revenue, 2),
            'orders'        => (float) $orders,
            'reliable_from' => $window > 0 ? $startedAt->copy()->addDays($window)->toDateString() : null,
        ];
    }
    /**
     * The first day a direct arrival was counted for these shops. Direct visits began later than the
     * other channels', so the figure needs its own start date beside it or it reads as tiny.
     *
     * @param array<int, int> $shopIds
     */
    protected function directVisitsSince(array $shopIds): ?string
    {
        return DB::table('traffic_source_visits as v')
            ->join('traffic_sources as ts', 'ts.id', '=', 'v.traffic_source_id')
            ->whereIn('v.shop_id', $shopIds)
            ->where('ts.type', TrafficSourcesTypeEnum::DIRECT->value)
            ->min('v.date');
    }
    /**
     * People each referring host sent, per browser per day, from the click log: the log keeps every
     * arrival with the host that sent it, so an assistant or a search engine can have its own visit
     * count and its own history without a second counter. Bots are left out, as they are of the
     * channel counter. Keyed by host.
     *
     * @param array<int, int> $shopIds
     *
     * @return \Illuminate\Support\Collection<string, int>
     */
    protected function visitsByHost(array $shopIds, ?Carbon $from, ?Carbon $to): \Illuminate\Support\Collection
    {
        return DB::table('traffic_source_clicks')
            ->whereIn('shop_id', $shopIds)
            ->whereIn('type', TrafficSourcesTypeEnum::hostReferencedValues())
            ->whereNotNull('campaign_ref')
            ->where('is_bot', false)
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
            ->groupBy('campaign_ref')
            ->select('campaign_ref', DB::raw('COUNT(DISTINCT (ip, user_agent, created_at::date)) as visits'))
            ->pluck('visits', 'campaign_ref');
    }
}
