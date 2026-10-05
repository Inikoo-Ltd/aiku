<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\McpChange\UI;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\UI\IndexMcpRequests;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Http\Resources\SysAdmin\McpChangesResource;
use App\InertiaTable\InertiaTable;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\McpChange;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexMcpChanges extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        $user = $request->user();

        return $user->authTo('sysadmin.view')
            || collect(McpChangeTypeEnum::cases())->contains(fn (McpChangeTypeEnum $type) => $user->{$type->userSwitch()});
    }

    protected function getElementGroups(Group $group): array
    {
        $base = McpChange::where('mcp_changes.group_id', $group->id);

        return [
            'status' => [
                'label'    => __('Status'),
                'elements' => [
                    'live'     => [__('Live'), (clone $base)->whereNull('reverted_at')->count()],
                    'reverted' => [__('Reverted'), (clone $base)->whereNotNull('reverted_at')->count()],
                ],
                'engine'   => function ($query, $elements) {
                    if (count($elements) === 1) {
                        array_pop($elements) === 'reverted'
                            ? $query->whereNotNull('mcp_changes.reverted_at')
                            : $query->whereNull('mcp_changes.reverted_at');
                    }
                },
            ],
            'type' => [
                'label'    => __('Type'),
                'elements' => collect(McpChangeTypeEnum::labels())
                    ->mapWithKeys(fn ($label, $type) => [$type => [$label, (clone $base)->where('type', $type)->count()]])
                    ->all(),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('mcp_changes.type', $elements);
                },
            ],
        ];
    }

    public function handle(Group $group, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereRaw('mcp_changes.label ILIKE ?', ["%$value%"])
                    ->orWhereRaw('mcp_changes.request_text ILIKE ?', ["%$value%"])
                    ->orWhereRaw('mcp_changes.data::text ILIKE ?', ["%$value%"])
                    ->orWhereRaw('users.username ILIKE ?', ["%$value%"]);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(McpChange::class)
            ->where('mcp_changes.group_id', $group->id)
            ->leftJoin('users', 'users.id', '=', 'mcp_changes.user_id')
            ->leftJoin('users as reverters', 'reverters.id', '=', 'mcp_changes.reverted_by_id');

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
                'mcp_changes.*',
                'users.username',
                'reverters.username as reverted_by_username',
            ])
            ->allowedSorts(['created_at', 'username', 'type', 'reverted_at'])
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
                ->withEmptyState(['title' => __('No changes made by AI assistants yet')])
                ->withLabelRecord([__('change'), __('changes')])
                ->column(key: 'created_at', label: __('Date'), canBeHidden: false, sortable: true)
                ->column(key: 'username', label: __('Asked by'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'label', label: __('Change'), canBeHidden: false, searchable: true)
                ->column(key: 'request_text', label: __('Request'), canBeHidden: false, searchable: true)
                ->column(key: 'before_after', label: __('Before → after'), canBeHidden: false)
                ->column(key: 'reverted_at', label: __('Status'), canBeHidden: false, sortable: true)
                ->defaultSort('-created_at');
        };
    }

    public function jsonResponse(LengthAwarePaginator $mcpChanges): AnonymousResourceCollection
    {
        return McpChangesResource::collection($mcpChanges);
    }

    public function htmlResponse(LengthAwarePaginator $mcpChanges, ActionRequest $request): Response
    {
        return Inertia::render(
            'SysAdmin/McpChanges',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('AI changes'),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-history'],
                        'title' => __('AI changes'),
                    ],
                    'title' => __('AI changes'),
                ],
                'data'        => McpChangesResource::collection($mcpChanges),
            ]
        )->table($this->tableStructure($this->group));
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            IndexMcpRequests::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'grp.sysadmin.mcp.changes.index',
                        ],
                        'label' => __('AI changes'),
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
