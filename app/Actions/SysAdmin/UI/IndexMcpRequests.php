<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\UI;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\GetMcpAnalytics;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Http\Resources\SysAdmin\McpRequestsResource;
use App\Http\Resources\SysAdmin\McpRequestUsersResource;
use App\InertiaTable\InertiaTable;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\McpRequest;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexMcpRequests extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('sysadmin.view');
    }

    protected function getElementGroups(Group $group): array
    {
        $base = McpRequest::where('mcp_requests.group_id', $group->id);

        return [
            'result' => [
                'label'    => __('Result'),
                'elements' => [
                    'ok'    => [__('Ok'), (clone $base)->where('is_error', false)->count()],
                    'error' => [__('Error'), (clone $base)->where('is_error', true)->count()],
                ],
                'engine'   => function ($query, $elements) {
                    if (count($elements) === 1) {
                        $query->where('mcp_requests.is_error', array_pop($elements) === 'error');
                    }
                },
            ],
            'access' => [
                'label'    => __('Access'),
                'elements' => [
                    'tools' => [__('Tools'), (clone $base)->whereHas('user', fn ($query) => $query->where('can_use_mcp_sql', false))->count()],
                    'sql'   => [__('SQL'), (clone $base)->whereHas('user', fn ($query) => $query->where('can_use_mcp_sql', true))->count()],
                ],
                'engine'   => function ($query, $elements) {
                    if (count($elements) === 1) {
                        $query->where('users.can_use_mcp_sql', array_pop($elements) === 'sql');
                    }
                },
            ],
        ];
    }

    public function handle(Group $group, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereRaw('mcp_requests.tool ILIKE ?', ["%$value%"])
                    ->orWhereRaw('mcp_requests.arguments::text ILIKE ?', ["%$value%"])
                    ->orWhereRaw('users.username ILIKE ?', ["%$value%"]);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(McpRequest::class)
            ->where('mcp_requests.group_id', $group->id)
            ->leftJoin('users', 'users.id', '=', 'mcp_requests.user_id');

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
                'mcp_requests.id',
                'mcp_requests.tool',
                'mcp_requests.arguments',
                'mcp_requests.is_error',
                'mcp_requests.duration_ms',
                'mcp_requests.created_at',
                'users.username',
                'users.can_use_mcp_sql',
            ])
            ->allowedSorts(['tool', 'is_error', 'duration_ms', 'created_at', 'username'])
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

        return QueryBuilder::for(McpRequest::class)
            ->where('mcp_requests.group_id', $group->id)
            ->join('users', 'users.id', '=', 'mcp_requests.user_id')
            ->selectRaw('
                users.username,
                bool_or(users.can_use_mcp_sql) as sql_access,
                count(*) as calls,
                count(*) filter (where mcp_requests.is_error) as errors,
                count(distinct mcp_requests.tool) as tools_used,
                round(avg(mcp_requests.duration_ms)) as avg_ms,
                max(mcp_requests.created_at) as last_used_at
            ')
            ->groupBy('users.username')
            ->defaultSort('-calls')
            ->allowedSorts(['username', 'calls', 'errors', 'tools_used', 'avg_ms', 'last_used_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
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
                ->withTitle(title: __('AI queries'))
                ->withLabelRecord([__('query'), __('queries')])
                ->column(key: 'created_at', label: __('Date'), canBeHidden: false, sortable: true)
                ->column(key: 'username', label: __('User'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'tool', label: __('Tool'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'arguments', label: __('Arguments'), canBeHidden: false, searchable: true)
                ->column(key: 'is_error', label: __('Result'), canBeHidden: false, sortable: true)
                ->column(key: 'duration_ms', label: __('Duration'), canBeHidden: false, sortable: true, align: 'right')
                ->defaultSort('-created_at');
        };
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
                ->withTitle(title: __('AI usage by user'))
                ->withLabelRecord([__('user'), __('users')])
                ->column(key: 'username', label: __('User'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'sql_access', label: __('Access'), canBeHidden: false)
                ->column(key: 'calls', label: __('Queries'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'errors', label: __('Errors'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'tools_used', label: __('Tools used'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'avg_ms', label: __('Avg time'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'last_used_at', label: __('Last used'), canBeHidden: false, sortable: true)
                ->defaultSort('-calls');
        };
    }

    public function jsonResponse(LengthAwarePaginator $mcpRequests): AnonymousResourceCollection
    {
        return McpRequestsResource::collection($mcpRequests);
    }

    public function htmlResponse(LengthAwarePaginator $mcpRequests, ActionRequest $request): Response
    {
        return Inertia::render(
            'SysAdmin/McpAnalytics',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('AI analytics'),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-robot'],
                        'title' => __('AI analytics'),
                    ],
                    'title' => __('AI analytics'),
                ],
                'insights' => GetMcpAnalytics::run($this->group),
                'data'     => McpRequestsResource::collection($mcpRequests),
                'users'    => McpRequestUsersResource::collection($this->handleUsers($this->group)),
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
                            'name' => 'grp.sysadmin.mcp.index',
                        ],
                        'label' => __('AI analytics'),
                    ]
                ]
            ]
        );
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group);
    }
}
