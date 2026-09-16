<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Tasks\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Http\Resources\Tasks\StaffTasksResource;
use App\InertiaTable\InertiaTable;
use App\Models\SysAdmin\Group;
use App\Models\Tasks\StaffTask;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexStaffTasks extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    protected function getElementGroups(Group $group): array
    {
        $base = StaffTask::where('staff_tasks.group_id', $group->id);

        return [
            'status' => [
                'label'    => __('Status'),
                'elements' => collect(StaffTaskStatusEnum::cases())->mapWithKeys(fn (StaffTaskStatusEnum $status) => [
                    $status->value => [StaffTaskStatusEnum::labels()[$status->value], (clone $base)->where('status', $status)->count()],
                ])->all(),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('staff_tasks.status', $elements);
                },
            ],
        ];
    }

    public function handle(Group $group, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($search) => $search
                ->where('staff_tasks.reference', 'ilike', "%$value%")
                ->orWhere('staff_tasks.subject', 'ilike', "%$value%"));
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(StaffTask::class)
            ->where('staff_tasks.group_id', $group->id)
            ->with(['requester', 'assignee']);

        foreach ($this->getElementGroups($group) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
                default: $key === 'status' ? StaffTaskStatusEnum::TODO->value.','.StaffTaskStatusEnum::IN_PROGRESS->value : null
            );
        }

        return $queryBuilder
            ->allowedFilters([$globalSearch])
            ->defaultSort('-staff_tasks.created_at')
            ->allowedSorts(['reference', 'subject', 'status', 'priority', 'due_at', 'created_at', 'closed_at'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group $group, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($group, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($group) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements'],
                    default: $key === 'status' ? StaffTaskStatusEnum::TODO->value.','.StaffTaskStatusEnum::IN_PROGRESS->value : null
                );
            }

            $table
                ->withGlobalSearch(__('Search tasks'))
                ->withLabelRecord([__('task'), __('tasks')])
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true, className: 'whitespace-nowrap w-px')
                ->column(key: 'subject', label: __('Subject'), canBeHidden: false, sortable: true, searchable: true, className: 'w-full max-w-0')
                ->column(key: 'status', label: __('Status'), canBeHidden: false, sortable: true, className: 'whitespace-nowrap w-px')
                ->column(key: 'priority', label: __('Priority'), icon: 'fal fa-flag', canBeHidden: false, sortable: true, className: 'w-px text-center')
                ->column(key: 'requester', label: __('Requester'), canBeHidden: false, className: 'whitespace-nowrap w-px')
                ->column(key: 'assignee', label: __('Assignee'), canBeHidden: false, className: 'whitespace-nowrap w-px')
                ->column(key: 'due_at', label: __('Due'), canBeHidden: false, sortable: true, type: 'date', className: 'whitespace-nowrap w-px')
                ->column(key: 'created_at', label: __('Created'), canBeHidden: false, sortable: true, type: 'date', className: 'whitespace-nowrap w-px')
                ->column(key: 'closed_at', label: __('Closed'), canBeHidden: false, sortable: true, type: 'date', className: 'whitespace-nowrap w-px')
                ->defaultSort('-created_at');
        };
    }

    public function jsonResponse(LengthAwarePaginator $staffTasks): AnonymousResourceCollection
    {
        return StaffTasksResource::collection($staffTasks);
    }

    public function htmlResponse(LengthAwarePaginator $staffTasks): Response
    {
        return Inertia::render(
            'Tasks/StaffTasksIndex',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('All tasks'),
                'pageHead'    => [
                    'title' => __('All tasks'),
                    'icon'  => ['fal', 'fa-tasks'],
                ],
                'data'        => StaffTasksResource::collection($staffTasks),
            ]
        )->table($this->tableStructure($this->group));
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-tasks',
                        'route' => ['name' => 'grp.tasks.index'],
                        'label' => __('Tasks'),
                    ],
                ],
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => ['name' => 'grp.tasks.list_all'],
                        'label' => __('All'),
                    ],
                ],
            ]
        );
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group);
    }
}
