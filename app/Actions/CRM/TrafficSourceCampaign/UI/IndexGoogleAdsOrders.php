<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 20 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithShopPpcPermissions;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\Ordering\Order;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexGoogleAdsOrders extends OrgAction
{
    use WithGoogleAdsInterval;
    use WithGoogleAdsProfit;
    use WithShopPpcPermissions;

    /**
     * @var array{orders: int, sales: float, profit: ?float, margin: ?float}|null
     */
    public ?array $totals = null;

    public function handle(Shop $shop, ?TrafficSourceCampaign $campaign = null, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('orders.reference', $value)
                    ->orWhereAnyWordStartWith('customers.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $adOrders = $this->googleAdsOrders($shop, campaignId: $campaign?->id);
        $orderIds = $adOrders->pluck('order_id')->all();

        $queryBuilder = QueryBuilder::for(Order::class)
            ->whereIn('orders.id', $orderIds)
            ->leftJoinSub($this->orderCampaigns($adOrders), 'order_campaigns', 'order_campaigns.order_id', '=', 'orders.id')
            ->leftJoin('traffic_source_campaigns', 'traffic_source_campaigns.id', '=', 'order_campaigns.traffic_source_campaign_id')
            ->leftJoinSub($this->orderProfitsTable($this->googleAdsProfits($shop)->only($orderIds)), 'order_profits', 'order_profits.order_id', '=', 'orders.id')
            ->leftJoin('customers', 'customers.id', '=', 'orders.customer_id');

        $queryBuilder = $queryBuilder
            ->defaultSort('-submitted_at')
            ->select([
                'orders.id',
                'orders.slug',
                'orders.reference',
                'orders.submitted_at',
                'orders.goods_amount as sales',
                'order_profits.profit',
                'order_profits.margin',
                'order_profits.is_estimated',
                'traffic_source_campaigns.name as campaign',
                'customers.name as customer_name',
                'customers.slug as customer_slug',
            ])
            ->allowedSorts(['reference', 'submitted_at', 'sales', 'profit', 'margin'])
            ->allowedFilters([$globalSearch]);

        $this->totals = $this->totalsOf($queryBuilder);

        return $queryBuilder
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * @return array{orders: int, sales: float, profit: ?float, margin: ?float}
     */
    private function totalsOf(QueryBuilder $queryBuilder): array
    {
        $query = (clone $queryBuilder)->getEloquentBuilder()->toBase();

        $query->columns = null;
        $query->orders  = null;

        $totals = $query
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('COALESCE(SUM(orders.goods_amount), 0) as sales')
            ->selectRaw('SUM(order_profits.costed_net) as costed_sales')
            ->selectRaw('SUM(order_profits.profit) as profit')
            ->first();

        $profit      = $totals->profit !== null ? (float) $totals->profit : null;
        $costedSales = (float) $totals->costed_sales;

        return [
            'orders' => (int) $totals->orders,
            'sales'  => (float) $totals->sales,
            'profit' => $profit,
            'margin' => $profit !== null && $costedSales != 0.0 ? round(100 * $profit / $costedSales, 1) : null,
        ];
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('order'), __('orders')])
                ->withEmptyState([
                    'title'       => __('No order from Google Ads in this period'),
                    'description' => __('An order is listed here when the last Google ad the customer clicked before submitting it belongs to this campaign.'),
                ])
                ->column(key: 'reference', label: __('Order'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'submitted_at', label: __('Submitted'), sortable: true)
                ->column(key: 'customer_name', label: __('Customer'), searchable: true)
                ->column(key: 'campaign', label: __('Campaign'), tooltip: __('The campaign of the last Google ad the customer clicked before submitting the order'), tooltipIcon: true)
                ->column(key: 'sales', label: __('Items net'), tooltip: __('What the items sold for after discounts, without shipping and charges, in the shop currency'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'profit', label: __('Profit'), tooltip: __('Items net minus what the stock cost, as on the order. Estimated from the last purchase price for what is not picked yet. Lines without a cost are left out. Shipping, payment fees and running costs are not taken off.'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'margin', label: __('Margin'), tooltip: __('Profit as a share of items net'), sortable: true, align: 'right', tooltipIcon: true)
                ->defaultSort('-submitted_at');
        };
    }
}
