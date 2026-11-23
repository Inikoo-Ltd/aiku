<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 20 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\UI;

use App\Actions\CRM\TrafficSource\ParseGoogleAdsClick;
use App\Actions\Traits\WithMarginData;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Orders that came from a Google ad and the item profit on them, worked out when the page is read.
 *
 * An order belongs, in full, to the campaign of the last Google ad click before it was submitted:
 * the click StampOrderGoogleAdsClick froze on the order, matched to its campaign through the click
 * log. That is how Google counts a conversion too. Orders without an ad click are not counted.
 *
 * The campaign Google reports for the click wins over the gad_campaignid of its URL, which for
 * Performance Max is an id Google does not know, and a campaign merged into another is read as it.
 *
 * Profit is what the margin box of the order shows: the picked cost of each line, topped up from
 * the last purchase price for what is not picked yet. Lines without a cost are left out, never
 * counted as free.
 *
 * The orders are read first and their profit is then worked out for those ids only: given the
 * orders as a subquery, Postgres guesses a handful of rows and plans a scan of every order line.
 *
 * Needs WithGoogleAdsInterval for the period and WithShopPpcPermissions for who may see profit.
 */
trait WithGoogleAdsProfit
{
    use WithMarginData;

    public bool $canSeeProfit = false;

    /**
     * @var array<string, Collection<int, object>>
     */
    private array $googleAdsOrdersRead = [];

    /**
     * @var array<string, Collection<int, object>>
     */
    private array $googleAdsProfitsRead = [];

    public function canSeeAdsProfit(User $user, Shop $shop): bool
    {
        return $this->canSeeMargins($shop) || $this->canEditGoogleAds($user, $shop);
    }

    /**
     * The orders submitted in the period after a Google ad click, each with the campaign of that
     * click, null when the click could not be matched to one.
     *
     * @return Collection<int, object{order_id: int, traffic_source_campaign_id: ?int, campaign: ?string}>
     */
    public function googleAdsOrders(Shop $shop, bool $previous = false, ?int $campaignId = null): Collection
    {
        $orders = $this->googleAdsOrdersRead[$shop->id.':'.(int) $previous] ??= $this->readGoogleAdsOrders($shop, $previous);

        return $campaignId ? $orders->where('traffic_source_campaign_id', $campaignId)->values() : $orders;
    }

    /**
     * Narrowed to a campaign in PHP: a campaign condition in the query sends the planner to a scan of
     * every order of the shop.
     *
     * @return Collection<int, object>
     */
    private function readGoogleAdsOrders(Shop $shop, bool $previous): Collection
    {
        $clicks = DB::table('traffic_source_clicks')
            ->where('shop_id', $shop->id)
            ->where('type', TrafficSourcesTypeEnum::GOOGLE_ADS->value)
            ->whereNotNull('click_id')
            ->whereRaw('COALESCE(google_campaign_ref, campaign_ref) IS NOT NULL')
            ->when($this->earliestClickAt($previous), fn (Builder $query, Carbon $earliest) => $query->where('created_at', '>=', $earliest))
            ->select('click_id', DB::raw('COALESCE(google_campaign_ref, campaign_ref) as campaign_ref'))
            ->distinct();

        $orders = DB::table('orders')
            ->leftJoinSub($clicks, 'ad_clicks', 'ad_clicks.click_id', '=', 'orders.google_ads_click_id')
            ->leftJoin('traffic_source_campaigns', function ($join) use ($shop) {
                $join->on('traffic_source_campaigns.reference', '=', 'ad_clicks.campaign_ref')
                    ->whereIn('traffic_source_campaigns.traffic_source_id', $this->googleAdsTrafficSourceIds($shop));
            })
            ->leftJoin('traffic_source_campaigns as merged_campaigns', 'merged_campaigns.id', '=', 'traffic_source_campaigns.merged_into_id')
            ->where('orders.shop_id', $shop->id)
            ->whereNotNull('orders.google_ads_click_id')
            ->whereNotIn('orders.state', $this->excludedOrderStates())
            ->select(
                'orders.id as order_id',
                'orders.goods_amount',
                DB::raw('COALESCE(merged_campaigns.id, traffic_source_campaigns.id) as traffic_source_campaign_id'),
                DB::raw('COALESCE(merged_campaigns.name, traffic_source_campaigns.name) as campaign')
            );

        return $this->wherePeriodOrPrevious($orders, 'orders.submitted_at', $previous)->get();
    }

    /**
     * Profit per order for the given orders: profit and costed_net in the order's currency, margin
     * in percent. An order none of whose lines could be costed has no row.
     *
     * @param array<int, int> $orderIds
     */
    public function orderProfits(array $orderIds): Builder
    {
        $lines = DB::table('transactions')
            ->leftJoin('assets', 'assets.id', '=', 'transactions.asset_id')
            ->leftJoin('products', function ($join) {
                $join->on('assets.model_id', '=', 'products.id')->where('assets.model_type', 'Product');
            })
            ->whereIn('transactions.order_id', $orderIds)
            ->where('transactions.model_type', 'Product')
            ->whereNull('transactions.deleted_at')
            ->select(
                'transactions.order_id',
                'transactions.net_amount',
                'transactions.org_net_amount',
                'transactions.quantity_picked',
                'transactions.quantity_ordered',
                DB::raw($this->actualCostSql('transactions.id').' as actual_cost'),
                DB::raw($this->estimatedCostSql('transactions.quantity_ordered').' as estimated_cost')
            );

        $isPartlyPicked = 'actual_cost IS NOT NULL AND quantity_picked IS NOT NULL AND quantity_ordered > 0 AND quantity_picked < quantity_ordered';

        $costedLines = DB::query()
            ->fromSub($lines, 'lines')
            ->whereNotNull('org_net_amount')
            ->select(
                'order_id',
                'net_amount',
                'org_net_amount',
                DB::raw("CASE
                    WHEN actual_cost IS NULL THEN estimated_cost
                    WHEN $isPartlyPicked THEN actual_cost + COALESCE(estimated_cost, 0) * (quantity_ordered - quantity_picked) / quantity_ordered
                    ELSE actual_cost
                END as cost"),
                DB::raw("(actual_cost IS NULL OR ($isPartlyPicked)) as is_estimated")
            );

        $totals = DB::query()
            ->fromSub($costedLines, 'costed_lines')
            ->whereNotNull('cost')
            ->groupBy('order_id')
            ->select(
                'order_id',
                DB::raw('SUM(net_amount) as costed_net'),
                DB::raw('SUM(org_net_amount) as costed_org_net'),
                DB::raw('SUM(cost) as cost'),
                DB::raw('BOOL_OR(is_estimated) as is_estimated')
            );

        return DB::query()
            ->fromSub($totals, 'order_costs')
            ->where('costed_org_net', '<>', 0)
            ->select(
                'order_id',
                'costed_net',
                'is_estimated',
                DB::raw('ROUND(costed_net * (costed_org_net - cost) / costed_org_net, 2) as profit'),
                DB::raw('ROUND(100 * (costed_org_net - cost) / costed_org_net, 1) as margin')
            );
    }

    /**
     * Orders, sales and profit per campaign for the period, as a table to join onto the campaigns
     * listing so its columns sort like the others.
     */
    public function periodCampaignOrders(Shop $shop, bool $previous = false): Builder
    {
        $orders  = $this->googleAdsOrders($shop, $previous)->whereNotNull('traffic_source_campaign_id');
        $profits = $this->googleAdsProfits($shop, $previous);

        $rows = $orders->groupBy('traffic_source_campaign_id')->map(function (Collection $campaignOrders, int $campaignId) use ($profits) {
            $costed = $campaignOrders->filter(fn ($order) => $profits->has($order->order_id));

            return [
                $campaignId,
                $campaignOrders->count(),
                round((float) $campaignOrders->sum('goods_amount'), 2),
                $costed->isEmpty() ? null : round((float) $costed->sum(fn ($order) => $profits[$order->order_id]->profit), 2),
            ];
        })->values();

        return $this->valuesTable(
            'campaign_orders',
            ['traffic_source_campaign_id' => 'integer', 'ad_orders' => 'integer', 'ad_sales' => 'numeric', 'profit' => 'numeric'],
            $rows->all()
        );
    }

    /**
     * The profit of every Google Ads order of the period, keyed by order id, worked out once per
     * request however many figures on the page are drawn from it.
     *
     * @return Collection<int, object{order_id: int, costed_net: string, is_estimated: bool, profit: string, margin: string}>
     */
    public function googleAdsProfits(Shop $shop, bool $previous = false): Collection
    {
        return $this->googleAdsProfitsRead[$shop->id.':'.(int) $previous] ??= $this->profitsByOrder($this->googleAdsOrders($shop, $previous));
    }

    /**
     * Profits already worked out, as a table to join the orders listing onto so it sorts by them.
     *
     * @param Collection<int, object> $profits
     */
    public function orderProfitsTable(Collection $profits): Builder
    {
        return $this->valuesTable(
            'order_profits',
            ['order_id' => 'integer', 'costed_net' => 'numeric', 'is_estimated' => 'boolean', 'profit' => 'numeric', 'margin' => 'numeric'],
            $profits->map(fn ($profit) => [$profit->order_id, $profit->costed_net, $profit->is_estimated, $profit->profit, $profit->margin])->values()->all()
        );
    }

    /**
     * Which campaign each order belongs to, as a table to join the orders listing onto.
     *
     * @param Collection<int, object> $orders
     */
    public function orderCampaigns(Collection $orders): Builder
    {
        return $this->valuesTable(
            'order_campaigns',
            ['order_id' => 'integer', 'traffic_source_campaign_id' => 'integer'],
            $orders->map(fn ($order) => [$order->order_id, $order->traffic_source_campaign_id])->all()
        );
    }

    /**
     * Rows worked out in PHP, as a table Postgres can join and sort on.
     *
     * @param array<string, string> $columns
     * @param array<int, array<int, mixed>> $rows
     */
    private function valuesTable(string $alias, array $columns, array $rows): Builder
    {
        $names = implode(', ', array_keys($columns));

        if ($rows === []) {
            $nulls = collect($columns)->map(fn (string $type, string $name) => "NULL::$type as $name")->implode(', ');

            return DB::query()->fromRaw("(SELECT $nulls WHERE false) as $alias");
        }

        $placeholders = '('.collect($columns)->map(fn (string $type) => "?::$type")->implode(', ').')';

        return DB::query()->fromRaw(
            "(VALUES ".implode(', ', array_fill(0, count($rows), $placeholders)).") as $alias($names)",
            array_merge(...array_map('array_values', $rows))
        );
    }

    /**
     * @return array{spend: float, orders: int, orders_without_profit: int, sales: float, profit: ?float, margin: ?float, profit_after_spend: ?float, poas: ?float}
     */
    public function googleAdsProfitSummary(Shop $shop, bool $previous = false, ?int $campaignId = null): array
    {
        $orders  = $this->googleAdsOrders($shop, $previous, $campaignId);
        $profits = $this->googleAdsProfits($shop, $previous)->only($orders->pluck('order_id')->all());

        $spendQuery = DB::table('traffic_source_costs')
            ->whereIn('traffic_source_costs.traffic_source_id', $this->googleAdsTrafficSourceIds($shop))
            ->when($campaignId, fn (Builder $query) => $query->where('traffic_source_costs.traffic_source_campaign_id', $campaignId));

        $spend = (float) $this->wherePeriodOrPrevious($spendQuery, 'traffic_source_costs.date', $previous)->sum('traffic_source_costs.amount');

        $profit      = $profits->isEmpty() ? null : round((float) $profits->sum('profit'), 2);
        $costedSales = (float) $profits->sum('costed_net');

        return [
            'spend'                 => $spend,
            'orders'                => $orders->count(),
            'orders_without_profit' => $orders->count() - $profits->count(),
            'sales'                 => round((float) $orders->sum('goods_amount'), 2),
            'profit'                => $profit,
            'margin'                => $profit !== null && $costedSales != 0.0 ? round(100 * $profit / $costedSales, 1) : null,
            'profit_after_spend'    => $profit !== null ? round($profit - $spend, 2) : null,
            'poas'                  => $profit !== null && $spend > 0 ? round($profit / $spend, 2) : null,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function excludedOrderStates(): array
    {
        return [OrderStateEnum::CREATING->value, OrderStateEnum::CANCELLED->value];
    }

    /**
     * @param Collection<int, object> $orders
     * @return Collection<int, object>
     */
    private function profitsByOrder(Collection $orders): Collection
    {
        if ($orders->isEmpty()) {
            return collect();
        }

        return $this->orderProfits($orders->pluck('order_id')->all())->get()->keyBy('order_id');
    }

    private function googleAdsTrafficSourceIds(Shop $shop): Builder
    {
        return DB::table('traffic_sources')
            ->where('shop_id', $shop->id)
            ->where('type', TrafficSourcesTypeEnum::GOOGLE_ADS->value)
            ->select('id');
    }

    /**
     * The click log is read from one attribution window before the period starts, which keeps the
     * match to an index range instead of the whole log. "All" reads every click.
     */
    private function earliestClickAt(bool $previous): ?Carbon
    {
        if ($previous) {
            $start = $this->comparisonRange()[0] ?? null;
        } elseif ($custom = $this->customRange()) {
            $start = $custom[0];
        } elseif ($this->interval() === DateIntervalEnum::ALL) {
            $start = null;
        } else {
            $start = Carbon::parse($this->intervalDates()[0]);
        }

        return $start?->copy()->startOfDay()->subDays(ParseGoogleAdsClick::WINDOW_DAYS + 1);
    }
}
