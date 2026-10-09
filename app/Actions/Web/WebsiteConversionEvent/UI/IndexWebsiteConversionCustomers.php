<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsiteConversionEvent\UI;

use App\Actions\OrgAction;
use App\Enums\Web\WebsiteConversionEvent\WebsiteConversionEventTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Customer;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWebsiteConversionCustomers extends OrgAction
{
    public function handle(Website $website, ?string $fromDate = null, ?string $toDate = null, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $value = strip_tags($value);
                $query->whereAnyWordStartWith('customers.name', $value)
                    ->orWhereAnyWordStartWith('customers.contact_name', $value)
                    ->orWhereStartWith('customers.email', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $checkout = WebsiteConversionEventTypeEnum::CHECKOUT->value;
        $purchase = WebsiteConversionEventTypeEnum::PURCHASE->value;

        $conversions = DB::table('website_conversion_events')
            ->join('orders', 'orders.id', '=', 'website_conversion_events.order_id')
            ->join('website_visitors', 'website_visitors.id', '=', 'website_conversion_events.website_visitor_id')
            ->where('website_conversion_events.website_id', $website->id)
            ->whereIn('website_conversion_events.event_type', [$checkout, $purchase])
            ->when($fromDate, fn ($query) => $query->where('website_conversion_events.event_date', '>=', Carbon::parse($fromDate)->toDateString()))
            ->when($toDate, fn ($query) => $query->where('website_conversion_events.event_date', '<=', Carbon::parse($toDate)->toDateString()))
            ->groupBy('orders.customer_id')
            ->select('orders.customer_id')
            ->selectRaw("SUM(CASE WHEN website_conversion_events.event_type = '$checkout' THEN 1 ELSE 0 END) as checkouts")
            ->selectRaw("SUM(CASE WHEN website_conversion_events.event_type = '$purchase' THEN 1 ELSE 0 END) as purchases")
            ->selectRaw("SUM(CASE WHEN website_conversion_events.event_type = '$purchase' THEN website_conversion_events.net_amount ELSE 0 END) as revenue")
            ->selectRaw('MAX(website_conversion_events.created_at) as last_event_at')
            ->selectRaw('STRING_AGG(DISTINCT website_visitors.traffic_source_type, \',\') as traffic_source_types');

        return QueryBuilder::for(Customer::class)
            ->joinSub($conversions, 'conversions', 'conversions.customer_id', '=', 'customers.id')
            ->leftJoin('organisations', 'customers.organisation_id', '=', 'organisations.id')
            ->leftJoin('shops', 'customers.shop_id', '=', 'shops.id')
            ->defaultSort('-last_event_at')
            ->select([
                'customers.id',
                'customers.slug',
                'customers.name',
                'customers.contact_name',
                'customers.email',
                'organisations.slug as organisation_slug',
                'shops.slug as shop_slug',
                'conversions.checkouts',
                'conversions.purchases',
                'conversions.revenue',
                'conversions.last_event_at',
                'conversions.traffic_source_types',
            ])
            ->allowedSorts(['name', 'checkouts', 'purchases', 'revenue', 'last_event_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
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
                ->withLabelRecord([__('customer'), __('customers')])
                ->withEmptyState([
                    'title'       => __('No customer reached the checkout in this period'),
                    'description' => __('Checkouts and purchases are recorded from the website, so orders placed by staff or through a sales channel are not listed.'),
                ])
                ->column(key: 'name', label: __('Customer'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'traffic_source_types', label: __('Channel'), tooltip: __('Where the visits that checked out or purchased came from'), tooltipIcon: true)
                ->column(key: 'checkouts', label: __('Checkouts'), tooltip: __('Orders this customer opened the checkout for, counted once per order and visit'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'purchases', label: __('Purchases'), tooltip: __('Orders submitted, counted once per order'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'revenue', label: __('Revenue'), tooltip: __('Net amount of the orders submitted, in the shop currency'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'last_event_at', label: __('Last activity'), sortable: true, align: 'right')
                ->defaultSort('-last_event_at');
        };
    }
}
