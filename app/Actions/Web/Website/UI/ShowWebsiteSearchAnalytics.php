<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\OrgAction;
use App\Actions\Search\GetWebsiteSearchAnalytics;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Website\UpdateWebsiteSearchBoosts;
use App\Actions\Web\Website\WithWebsiteAnalyticsSubNavigation;
use App\Http\Resources\Web\WebsiteSearchLogCustomersResource;
use App\Http\Resources\Web\WebsiteSearchLogsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\WebsiteSearchLog;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class ShowWebsiteSearchAnalytics extends OrgAction
{
    use WithWebAuthorisation;
    use WithWebsiteAnalyticsSubNavigation;
    use WithWebsiteSearchLogsTable;

    private Website $website;

    public function handle(Website $website, $prefix = null): LengthAwarePaginator
    {
        return $this->websiteSearchLogsQuery($website, null, $prefix);
    }

    public function handleCustomers(Website $website, $prefix = 'customers'): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereRaw('customers.name ILIKE ?', ["%$value%"]);
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(WebsiteSearchLog::class)
            ->where('website_search_logs.website_id', $website->id)
            ->join('customers', 'customers.id', '=', 'website_search_logs.customer_id')
            ->selectRaw('customers.name as customer_name, customers.slug as customer_slug, count(*) as searches, count(website_search_logs.clicked_at) as clicks, count(*) filter (where website_search_logs.results_count = 0) as zero_results, max(website_search_logs.created_at) as last_searched_at')
            ->groupBy('customers.name', 'customers.slug')
            ->defaultSort('-searches')
            ->allowedSorts(['customer_name', 'searches', 'clicks', 'zero_results', 'last_searched_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function customersTableStructure($prefix = 'customers'): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withTitle(title: __('Searches by customer'))
                ->withLabelRecord([__('customer'), __('customers')])
                ->column(key: 'customer_name', label: __('Customer'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'searches', label: __('Searches'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'clicks', label: __('Clicks'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'click_through', label: __('CTR'), canBeHidden: false, align: 'right')
                ->column(key: 'zero_results', label: __('No results'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'last_searched_at', label: __('Last search'), canBeHidden: false, sortable: true)
                ->defaultSort('-searches');
        };
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->website = $website;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($website);
    }

    public function htmlResponse(LengthAwarePaginator $searchLogs, ActionRequest $request): Response
    {
        $title = __('Website Search');

        return Inertia::render(
            'Org/Web/WebsiteSearchAnalytics',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-satellite-dish'],
                        'title' => __('Comms')
                    ],
                    'iconRight'     => [
                        'icon'  => ['fal', 'fa-search'],
                        'title' => $title
                    ],
                    'title'         => $title,
                    'subNavigation' => $this->getWebsiteAnalyticsNavigation($this->website),
                ],
                'search_insights' => GetWebsiteSearchAnalytics::run($this->website),
                'search_boosts'   => $this->getSearchBoosts(),
                'boost_routes'    => [
                    'update'     => [
                        'name'       => 'grp.org.shops.show.web.analytics.search.boosts.update',
                        'parameters' => $request->route()->originalParameters(),
                    ],
                    'candidates' => [
                        'name'       => 'grp.org.shops.show.web.analytics.search.boost_candidates',
                        'parameters' => $request->route()->originalParameters(),
                    ],
                ],
                'drilldown'       => [
                    'query'    => 'grp.org.shops.show.web.analytics.search.query',
                    'customer' => 'grp.org.shops.show.web.analytics.search.customer',
                    'params'   => $request->route()->originalParameters(),
                ],
                'data'            => WebsiteSearchLogsResource::collection($searchLogs),
                'customers'       => WebsiteSearchLogCustomersResource::collection($this->handleCustomers($this->website)),
            ]
        )->table($this->websiteSearchLogsTableStructure($this->website))
            ->table($this->customersTableStructure());
    }

    /**
     * @return array<int, array{type: string, id: int, code: string, name: string|null}>
     */
    private function getSearchBoosts(): array
    {
        $boosts = Arr::get($this->website->settings, 'search_boosts', []);

        return array_values(array_filter(array_map(function (array $boost) {
            $modelClass = Arr::get(UpdateWebsiteSearchBoosts::BOOSTABLE_TYPES, $boost['type']);
            $model      = $modelClass ? $modelClass::find($boost['id']) : null;

            if (!$model) {
                return null;
            }

            return [
                'type' => $boost['type'],
                'id'   => $model->id,
                'code' => $model->code,
                'name' => $model->name,
            ];
        }, $boosts)));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        /** @var Website $website */
        $website = request()->route()->parameter('website');

        return array_merge(
            ShowWebsite::make()->getBreadcrumbs($website, 'grp.org.shops.show.web.websites.show', $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.web.analytics.search',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Website Search'),
                    ]
                ]
            ]
        );
    }
}
