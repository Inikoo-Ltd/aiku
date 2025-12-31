<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsiteVisitor\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Website\UI\ShowWebsiteAnalyticsDashboard;
use App\Actions\Web\Website\WithWebsiteAnalyticsSubNavigation;
use App\Http\Resources\Web\WebsiteVisitorResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use App\Models\Web\WebsiteVisitor;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWebsiteVisitors extends OrgAction
{
    use WithWebAuthorisation;
    use WithWebsiteAnalyticsSubNavigation;

    public function handle(Website $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('website_visitors.session_id', $value)
                    ->orWhereStartWith('website_visitors.ip_hash', $value)
                    ->orWhereStartWith('website_visitors.country_code', $value)
                    ->orWhereStartWith('website_visitors.city', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(WebsiteVisitor::class);
        $queryBuilder->where('website_visitors.website_id', $parent->id);

        return $queryBuilder
            ->defaultSort('-first_seen_at')
            ->select([
                'website_visitors.*'
            ])
            ->allowedSorts(['first_seen_at', 'last_seen_at', 'page_views', 'duration_seconds', 'device_type', 'country_code'])
            ->allowedFilters([$globalSearch, 'device_type', 'browser', 'os', 'country_code', 'is_bounce', 'is_new_visitor'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
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
                ->column(key: 'session_id', label: __('Session ID'), canBeHidden: false, searchable: true)
                ->column(key: 'device_type', label: __('Device'), canBeHidden: false, sortable: true)
                ->column(key: 'browser', label: __('Browser'), canBeHidden: false)
                ->column(key: 'os', label: __('OS'), canBeHidden: false)
                ->column(key: 'location', label: __('Location'), canBeHidden: false)
                ->column(key: 'page_views', label: __('Page Views'), canBeHidden: false, sortable: true)
                ->column(key: 'duration', label: __('Duration'), canBeHidden: false, sortable: true)
                ->column(key: 'bounce', label: __('Bounce'), canBeHidden: false)
                ->column(key: 'first_seen_at', label: __('First Seen'), canBeHidden: false, sortable: true)
                ->column(key: 'last_seen_at', label: __('Last Seen'), canBeHidden: false, sortable: true)
                ->defaultSort('-first_seen_at');
        };
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($website);
    }

    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($website);
    }

    public function htmlResponse(LengthAwarePaginator $visitors, ActionRequest $request): Response
    {
        /** @var Website $website */
        $website = $request->route()->parameter('website');
        $title   = __('Website Visitors');

        return Inertia::render(
            'Org/Web/WebsiteVisitors',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'title'         => $title,
                    'subNavigation' => $this->getWebsiteAnalyticsNavigation($website),
                ],
                'data'        => WebsiteVisitorResource::collection($visitors),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        if ($routeName == 'grp.org.shops.show.web.analytics.visitors.index') {
            return array_merge(
                ShowWebsiteAnalyticsDashboard::make()->getBreadcrumbs('grp.org.shops.show.web.analytics.dashboard', $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.web.analytics.visitors.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Website Visitors'),
                        ]
                    ]
                ]
            );
        } else {
            return array_merge(
                ShowWebsiteAnalyticsDashboard::make()->getBreadcrumbs('grp.org.fulfilments.show.web.analytics.dashboard', $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.web.analytics.visitors.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Website Visitors'),
                        ]
                    ]
                ]
            );
        }
    }
}
