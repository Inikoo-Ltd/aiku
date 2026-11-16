<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo\UI;

use App\Actions\CRM\TrafficSource\GetAttributionWindow;
use App\Actions\CRM\TrafficSource\WithAttributionWindow;
use App\Actions\OrgAction;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Web\SeoReferringDomain;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexSeoReferringDomains extends OrgAction
{
    use WithAttributionWindow;

    private const string HOST = "regexp_replace(lower(seo_referring_domains.referring_domain), '^www\\.', '')";

    /**
     * @param  Carbon|null  $newSince  start of the latest weekly run, null when it was the first one
     */
    public function handle(Shop $shop, string $domain, ?Carbon $newSince, ?string $prefix = null): LengthAwarePaginator
    {
        $window = GetAttributionWindow::run($shop);

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where('referring_domain', 'ilike', '%'.addcslashes(strip_tags($value), '%_\\').'%');
        });

        return QueryBuilder::for(SeoReferringDomain::class)
            ->where('domain', $domain)
            ->leftJoinSub($this->visitsByReference($shop), 'visits', 'visits.reference', '=', DB::raw(self::HOST))
            ->leftJoinSub($this->customersByReference($shop), 'customers', 'customers.reference', '=', DB::raw(self::HOST))
            ->leftJoinSub($this->ordersByReference($shop, $window), 'orders', 'orders.reference', '=', DB::raw(self::HOST))
            ->leftJoinSub($this->revenueByReference($shop, $window), 'revenue', 'revenue.reference', '=', DB::raw(self::HOST))
            ->defaultSort('-rank')
            ->select(['seo_referring_domains.id', 'referring_domain', 'rank', 'backlinks', 'is_own_website', 'first_seen', 'first_fetched_at', 'lost_at'])
            ->selectRaw('COALESCE(visits.visits, 0) AS visits')
            ->selectRaw('COALESCE(customers.customers, 0) AS customers')
            ->selectRaw('COALESCE(orders.orders, 0) AS orders')
            ->selectRaw('COALESCE(revenue.revenue, 0) AS revenue')
            ->allowedSorts([
                AllowedSort::field('referring_domain'),
                AllowedSort::callback('rank', fn ($query, bool $descending) => $query->orderByRaw('rank '.($descending ? 'DESC' : 'ASC').' NULLS LAST')),
                AllowedSort::field('backlinks'),
                AllowedSort::field('first_seen'),
                AllowedSort::field('visits'),
                AllowedSort::field('customers'),
                AllowedSort::field('orders'),
                AllowedSort::field('revenue'),
            ])
            ->allowedFilters([
                $globalSearch,
                AllowedFilter::callback('status', fn ($query, $value) => match ($value) {
                    'new'   => $newSince ? $query->whereNull('lost_at')->where('first_fetched_at', '>=', $newSince) : $query->whereRaw('false'),
                    'lost'  => $query->whereNotNull('lost_at'),
                    default => $query->whereNull('lost_at'),
                })->default('live'),
                AllowedFilter::callback('websites', fn ($query, $value) => $value === 'include' ? $query : $query->where('is_own_website', false))->default('exclude'),
            ])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    private function visitsByReference(Shop $shop): Builder
    {
        return DB::table('traffic_source_clicks')
            ->where('shop_id', $shop->id)
            ->whereIn('type', TrafficSourcesTypeEnum::hostReferencedValues())
            ->whereNotNull('campaign_ref')
            ->where('is_bot', false)
            ->groupBy('campaign_ref')
            ->select('campaign_ref as reference', DB::raw('COUNT(DISTINCT (ip, user_agent, created_at::date)) AS visits'));
    }

    private function campaignTouches(Shop $shop, string $modelType): Builder
    {
        return DB::table('model_has_traffic_sources as p')
            ->join('traffic_source_campaigns as c', 'c.id', '=', 'p.traffic_source_campaign_id')
            ->join('traffic_sources as ts', 'ts.id', '=', 'c.traffic_source_id')
            ->where('p.model_type', $modelType)
            ->where('ts.shop_id', $shop->id)
            ->whereIn('ts.type', TrafficSourcesTypeEnum::hostReferencedValues())
            ->groupBy('c.reference');
    }

    private function customersByReference(Shop $shop): Builder
    {
        return $this->campaignTouches($shop, 'Customer')
            ->select('c.reference', DB::raw('SUM(p.share) AS customers'));
    }

    private function ordersByReference(Shop $shop, int $window): Builder
    {
        return $this->campaignTouches($shop, 'Customer')
            ->join('orders', 'orders.customer_id', '=', 'p.model_id')
            ->where(fn ($query) => $this->constrainToTouchWindow($query, self::ORDER_PLACED_AT, $window))
            ->where('orders.shop_id', $shop->id)
            ->whereNotIn('orders.state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])
            ->whereNull('orders.deleted_at')
            ->select('c.reference', DB::raw('SUM(p.share) AS orders'));
    }

    private function revenueByReference(Shop $shop, int $window): Builder
    {
        return $this->campaignTouches($shop, 'Customer')
            ->join('invoices', 'invoices.customer_id', '=', 'p.model_id')
            ->where(fn ($query) => $this->constrainToAttributionWindow($query, $window))
            ->where('invoices.shop_id', $shop->id)
            ->where('invoices.in_process', false)
            ->select('c.reference', DB::raw('SUM(invoices.net_amount * p.share) AS revenue'));
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
                ->withLabelRecord([__('referring domain'), __('referring domains')])
                ->withEmptyState([
                    'title'       => __('No referring domains here'),
                    'description' => __('Referring domains are fetched every week. Try another status, or include our own websites.'),
                ])
                ->column(key: 'referring_domain', label: __('Referring domain'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'rank', label: __('Rank'), tooltip: __('DataForSEO domain rank, 0 to 100. Not the Semrush Authority Score or Moz DA, so compare trends, not values'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'backlinks', label: __('Backlinks'), tooltip: __('Links from this domain to our website'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'first_seen', label: __('First seen'), tooltip: __('When DataForSEO first found a link from this domain'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'visits', label: __('Visits'), tooltip: __('Visits that arrived from a link on this domain'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'customers', label: __('Customers'), tooltip: __('Customers who came from this domain, split when they also came from other sources'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'orders', label: __('Orders'), tooltip: __('Orders placed by those customers within the attribution window'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'revenue', label: __('Revenue'), tooltip: __('Invoiced net sales credited to this domain within the attribution window'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'status', label: __('Status'))
                ->defaultSort('-rank');
        };
    }
}
