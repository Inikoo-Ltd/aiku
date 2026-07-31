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
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class ShowWebsiteSearchAnalytics extends OrgAction
{
    use WithWebAuthorisation;
    use WithWebsiteAnalyticsSubNavigation;

    private Website $website;

    protected function getElementGroups(Website $website): array
    {
        $base = WebsiteSearchLog::where('website_id', $website->id);

        $deviceCounts = (clone $base)->whereNotNull('device')->selectRaw('device, count(*) as count')->groupBy('device')->pluck('count', 'device');

        return [
            'clicked' => [
                'label'    => __('Click'),
                'elements' => [
                    'clicked'     => [__('Clicked'), (clone $base)->whereNotNull('clicked_at')->count()],
                    'not_clicked' => [__('Not clicked'), (clone $base)->whereNull('clicked_at')->count()],
                ],
                'engine'   => function ($query, $elements) {
                    if (count($elements) === 1) {
                        array_pop($elements) === 'clicked'
                            ? $query->whereNotNull('website_search_logs.clicked_at')
                            : $query->whereNull('website_search_logs.clicked_at');
                    }
                },
            ],
            'results' => [
                'label'    => __('Results'),
                'elements' => [
                    'with_results' => [__('With results'), (clone $base)->where('results_count', '>', 0)->count()],
                    'no_results'   => [__('No results'), (clone $base)->where('results_count', 0)->count()],
                ],
                'engine'   => function ($query, $elements) {
                    if (count($elements) === 1) {
                        array_pop($elements) === 'with_results'
                            ? $query->where('website_search_logs.results_count', '>', 0)
                            : $query->where('website_search_logs.results_count', 0);
                    }
                },
            ],
            'logged'  => [
                'label'    => __('Visitor'),
                'elements' => [
                    'logged_in' => [__('Logged in'), (clone $base)->whereNotNull('web_user_id')->count()],
                    'guest'     => [__('Guest'), (clone $base)->whereNull('web_user_id')->count()],
                ],
                'engine'   => function ($query, $elements) {
                    if (count($elements) === 1) {
                        array_pop($elements) === 'logged_in'
                            ? $query->whereNotNull('website_search_logs.web_user_id')
                            : $query->whereNull('website_search_logs.web_user_id');
                    }
                },
            ],
            'device'  => [
                'label'    => __('Device'),
                'elements' => $deviceCounts->mapWithKeys(fn ($count, $device) => [$device => [$device, $count]])->all(),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('website_search_logs.device', $elements);
                },
            ],
        ];
    }

    public function handle(Website $website, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereRaw('website_search_logs.query ILIKE ?', ["%$value%"])
                    ->orWhereRaw('customers.name ILIKE ?', ["%$value%"]);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(WebsiteSearchLog::class)
            ->where('website_search_logs.website_id', $website->id)
            ->leftJoin('customers', 'customers.id', '=', 'website_search_logs.customer_id');

        foreach ($this->getElementGroups($website) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $queryBuilder
            ->defaultSort('-created_at')
            ->select([
                'website_search_logs.id',
                'website_search_logs.query',
                'website_search_logs.scope',
                'website_search_logs.device',
                'website_search_logs.browser',
                'website_search_logs.results_count',
                'website_search_logs.clicked_at',
                'website_search_logs.clicked_url',
                'website_search_logs.created_at',
                'customers.name as customer_name',
                'customers.slug as customer_slug',
            ])
            ->allowedSorts(['query', 'scope', 'device', 'results_count', 'clicked_at', 'created_at', 'customer_name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
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

    public function tableStructure(Website $website, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($website, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($website) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->withGlobalSearch()
                ->withTitle(title: __('Website search logs'))
                ->withLabelRecord([__('search'), __('searches')])
                ->column(key: 'created_at', label: __('Date'), canBeHidden: false, sortable: true)
                ->column(key: 'query', label: __('Query'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'scope', label: __('Section'), canBeHidden: false, sortable: true)
                ->column(key: 'customer_name', label: __('Customer'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'device', label: __('Device'), canBeHidden: false, sortable: true)
                ->column(key: 'results_count', label: __('Results'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'clicked_at', label: __('Clicked'), canBeHidden: false, sortable: true)
                ->defaultSort('-created_at');
        };
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
                'data'            => WebsiteSearchLogsResource::collection($searchLogs),
                'customers'       => WebsiteSearchLogCustomersResource::collection($this->handleCustomers($this->website)),
            ]
        )->table($this->tableStructure($this->website))
            ->table($this->customersTableStructure());
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
