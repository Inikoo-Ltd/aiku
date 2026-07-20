<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 05:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search\UI;

use App\Actions\GrpAction;
use App\Actions\Search\GetSearchAnalytics;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Http\Resources\SysAdmin\SearchLogsResource;
use App\Http\Resources\SysAdmin\SearchLogUsersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\SearchLog;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexSearchLogs extends GrpAction
{
    protected function getElementGroups(Group $group): array
    {
        $base = SearchLog::where('group_id', $group->id);

        $scopeCounts = (clone $base)->selectRaw('scope, count(*) as count')->groupBy('scope')->pluck('count', 'scope');

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
                            ? $query->whereNotNull('search_logs.clicked_at')
                            : $query->whereNull('search_logs.clicked_at');
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
                            ? $query->where('search_logs.results_count', '>', 0)
                            : $query->where('search_logs.results_count', 0);
                    }
                },
            ],
            'scope'   => [
                'label'    => __('Section'),
                'elements' => $scopeCounts->mapWithKeys(fn ($count, $scope) => [$scope => [$scope, $count]])->all(),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('search_logs.scope', $elements);
                },
            ],
        ];
    }

    public function handle(Group $group, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereRaw('search_logs.query ILIKE ?', ["%$value%"])
                    ->orWhereRaw('users.username ILIKE ?', ["%$value%"]);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(SearchLog::class)
            ->where('search_logs.group_id', $group->id)
            ->leftJoin('users', 'users.id', '=', 'search_logs.user_id')
            ->leftJoin('organisations', 'organisations.id', '=', 'search_logs.organisation_id')
            ->leftJoin('shops', 'shops.id', '=', 'search_logs.shop_id');

        foreach ($this->getElementGroups($group) as $key => $elementGroup) {
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
                'search_logs.id',
                'search_logs.query',
                'search_logs.scope',
                'search_logs.results_count',
                'search_logs.clicked_at',
                'search_logs.clicked_url',
                'search_logs.created_at',
                'users.username',
                'organisations.code as organisation_code',
                'shops.code as shop_code',
            ])
            ->allowedSorts(['query', 'scope', 'results_count', 'clicked_at', 'created_at', 'username'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function handleUsers(Group $group, $prefix = 'users'): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereRaw('users.username ILIKE ?', ["%$value%"]);
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(SearchLog::class)
            ->where('search_logs.group_id', $group->id)
            ->join('users', 'users.id', '=', 'search_logs.user_id')
            ->selectRaw('users.username, count(*) as searches, count(search_logs.clicked_at) as clicks, count(*) filter (where search_logs.results_count = 0) as zero_results, max(search_logs.created_at) as last_searched_at')
            ->groupBy('users.username')
            ->defaultSort('-searches')
            ->allowedSorts(['username', 'searches', 'clicks', 'zero_results', 'last_searched_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function usersTableStructure($prefix = 'users'): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withTitle(title: __('Searches by user'))
                ->withLabelRecord([__('user'), __('users')])
                ->column(key: 'username', label: __('User'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'searches', label: __('Searches'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'clicks', label: __('Clicks'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'click_through', label: __('CTR'), canBeHidden: false, align: 'right')
                ->column(key: 'zero_results', label: __('No results'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'last_searched_at', label: __('Last search'), canBeHidden: false, sortable: true)
                ->defaultSort('-searches');
        };
    }

    public function tableStructure(Group $group, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($group, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($group) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->withGlobalSearch()
                ->withTitle(title: __('Search logs'))
                ->withLabelRecord([__('search'), __('searches')])
                ->column(key: 'created_at', label: __('Date'), canBeHidden: false, sortable: true)
                ->column(key: 'query', label: __('Query'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'scope', label: __('Section'), canBeHidden: false, sortable: true)
                ->column(key: 'username', label: __('User'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'context', label: __('Context'), canBeHidden: false)
                ->column(key: 'results_count', label: __('Results'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'clicked_at', label: __('Clicked'), canBeHidden: false, sortable: true)
                ->defaultSort('-created_at');
        };
    }

    public function jsonResponse(LengthAwarePaginator $searchLogs): AnonymousResourceCollection
    {
        return SearchLogsResource::collection($searchLogs);
    }

    public function htmlResponse(LengthAwarePaginator $searchLogs, ActionRequest $request): Response
    {
        return Inertia::render(
            'SysAdmin/SearchLogs',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Search analytics'),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-search'],
                        'title' => __('Search analytics'),
                    ],
                    'title' => __('Search analytics'),
                ],
                'insights' => GetSearchAnalytics::run($this->group),
                'data'     => SearchLogsResource::collection($searchLogs),
                'users'    => SearchLogUsersResource::collection($this->handleUsers($this->group)),
            ]
        )->table($this->tableStructure($this->group))
            ->table($this->usersTableStructure());
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'grp.sysadmin.search_logs.index',
                        ],
                        'label' => __('Search analytics'),
                    ]
                ]
            ]
        );
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation(group(), $request);

        return $this->handle($this->group);
    }
}
