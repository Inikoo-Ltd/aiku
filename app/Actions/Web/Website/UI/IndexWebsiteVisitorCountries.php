<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use App\Models\Web\WebsiteVisitor;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Our beacon's visits per country and website over the dashboard's window, from the first whole
 * day the visitor rows still keep. Each row leads to that website's visitors of that country.
 */
class IndexWebsiteVisitorCountries extends OrgAction
{
    public function handle(Group $group, User $user, ?string $selected, ?int $days, ?string $day, ?string $country, $prefix = null): LengthAwarePaginator
    {
        [, $chosen] = GetWebsitesEdgeTraffic::make()->chosenWebsites($group, $user, $selected);
        [$from, $to] = GetWebsitesEdgeTraffic::make()->window($days, $day);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('website_visitors.country_code', $value)
                    ->orWhereStartWith('websites.domain', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(WebsiteVisitor::class)
            ->join('websites', 'websites.id', 'website_visitors.website_id')
            ->join('organisations', 'organisations.id', 'websites.organisation_id')
            ->join('shops', 'shops.id', 'websites.shop_id')
            ->leftJoin('fulfilments', 'fulfilments.shop_id', 'shops.id')
            ->whereIn('website_visitors.website_id', $chosen->pluck('id'))
            ->whereBetween('website_visitors.first_seen_at', [GetWebsitesEdgeTraffic::make()->beaconFrom($from), $to])
            ->when($country && preg_match('/^[A-Z]{2}$/', $country), fn ($query) => $query->where('website_visitors.country_code', $country))
            ->groupBy('website_visitors.country_code', 'websites.id', 'organisations.id', 'shops.id', 'fulfilments.id')
            ->defaultSort('-sessions')
            ->select([
                'website_visitors.country_code',
                'websites.slug as website_slug',
                'websites.domain',
                'organisations.slug as organisation_slug',
                'shops.slug as shop_slug',
                'shops.type as shop_type',
                'fulfilments.slug as fulfilment_slug',
                DB::raw('COUNT(*) as sessions'),
                DB::raw('COUNT(*) FILTER (WHERE website_visitors.web_user_id IS NOT NULL) as logged_in_sessions'),
                DB::raw('ROUND(COUNT(*) FILTER (WHERE website_visitors.web_user_id IS NOT NULL) * 100.0 / COUNT(*), 1) as logged_in_share'),
                DB::raw('SUM(website_visitors.page_views) as page_views'),
            ])
            ->allowedSorts(['country_code', 'domain', 'sessions', 'logged_in_sessions', 'logged_in_share', 'page_views'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('No visits'),
                    'description' => __('Our beacon keeps visits for 30 days'),
                ])
                ->column(key: 'country_code', label: __('Country'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'domain', label: __('Website'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'sessions', label: __('Visits'), sortable: true, align: 'right')
                ->column(key: 'logged_in_sessions', label: __('Logged in'), sortable: true, align: 'right')
                ->column(key: 'logged_in_share', label: __('Logged in %'), sortable: true, align: 'right')
                ->column(key: 'page_views', label: __('Page views'), sortable: true, align: 'right')
                ->defaultSort('-sessions');
        };
    }
}
