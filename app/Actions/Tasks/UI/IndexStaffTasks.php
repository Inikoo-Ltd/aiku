<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Tasks\UI;

use App\Actions\OrgAction;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Http\Resources\Tasks\StaffTasksResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexStaffTasks extends OrgAction
{
    use WithStaffTasksScope;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    protected function getElementGroups(Group|Organisation $parent, User $viewer): array
    {
        $base = StaffTask::query()->within($parent)->visibleTo($viewer);

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

    public function handle(Group|Organisation $parent, User $viewer, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($search) => $search
                ->where('staff_tasks.reference', 'ilike', "%$value%")
                ->orWhere('staff_tasks.subject', 'ilike', "%$value%"));
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for($this->applyAssigneeFilter(StaffTask::query()->within($parent)->visibleTo($viewer), $viewer, $this->appliedAssigneeFilter()))
            ->with(['requester.image', 'assignee.image', 'collaborators', 'conversation']);

        foreach ($this->getElementGroups($parent, $viewer) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
                default: $key === 'status' ? StaffTaskStatusEnum::TODO->value.','.StaffTaskStatusEnum::IN_PROGRESS->value : null
            );
        }

        $collaboratingOn = fn ($value) => DB::table('staff_task_collaborators')->where('user_id', (int) $value)->select('staff_task_id');

        return $queryBuilder
            ->allowedFilters([
                $globalSearch,
                AllowedFilter::callback('created_since', fn ($query, $value) => $query->where('staff_tasks.created_at', '>=', $value)),
                AllowedFilter::callback('created_before', fn ($query, $value) => $query->where('staff_tasks.created_at', '<', $value)),
                AllowedFilter::callback('closed_since', fn ($query, $value) => $query->where('staff_tasks.closed_at', '>=', $value)),
                AllowedFilter::callback('organisation', fn ($query) => $query),
                AllowedFilter::callback('assignee', fn ($query) => $query),
                AllowedFilter::callback('has_assignee', fn ($query, $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? $query->whereNotNull('staff_tasks.assignee_id') : $query),
                AllowedFilter::callback('collaborator', fn ($query, $value) => $query->whereIn('staff_tasks.id', $collaboratingOn($value))),
                AllowedFilter::callback('involved', fn ($query, $value) => $query->where(fn ($involved) => $involved->where('staff_tasks.assignee_id', (int) $value)->orWhereIn('staff_tasks.id', $collaboratingOn($value)))),
                AllowedFilter::callback('requester', fn ($query, $value) => $query->where('staff_tasks.requester_id', (int) $value)),
                AllowedFilter::callback('department', fn ($query, $value) => $this->applyDepartmentFilter($query, $value)),
                AllowedFilter::callback('unassigned', fn ($query, $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? $query->whereNull('staff_tasks.assignee_id') : $query),
                AllowedFilter::callback('overdue', fn ($query, $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? $query->where('staff_tasks.due_at', '<', today()) : $query),
            ])
            ->defaultSort('-staff_tasks.created_at')
            ->allowedSorts(['reference', 'subject', 'status', 'priority', 'due_at', 'created_at', 'closed_at'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Organisation $parent, User $viewer, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $viewer, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($parent, $viewer) as $key => $elementGroup) {
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
                ->column(key: 'requester', label: __('Requester'), canBeHidden: false, type: 'avatar', className: 'whitespace-nowrap w-px')
                ->column(key: 'assignee', label: __('Assignee'), canBeHidden: false, type: 'avatar', className: 'whitespace-nowrap w-px')
                ->column(key: 'due_at', label: __('Due'), canBeHidden: false, sortable: true, type: 'date', className: 'whitespace-nowrap w-px')
                ->column(key: 'created_at', label: __('Created'), canBeHidden: false, sortable: true, type: 'date', className: 'whitespace-nowrap w-px')
                ->defaultSort('-created_at');
        };
    }

    public function jsonResponse(LengthAwarePaginator $staffTasks): AnonymousResourceCollection
    {
        return StaffTasksResource::collection($staffTasks);
    }

    public function htmlResponse(LengthAwarePaginator $staffTasks, ActionRequest $request): Response
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
                'listSummary' => $this->listSummary($this->tasksListParent(), $request->user()),
                'options'     => $this->staffTaskEditOptions(),
                'taskFilterOptions'  => $this->taskFilterOptions($this->tasksListParent(), $request->user()),
                'appliedTaskFilters' => $this->appliedTaskFilters(),
                'showRoute'   => $this->tasksRoute('show'),
            ]
        )->table($this->tableStructure($this->tasksListParent(), $request->user()));
    }

    /**
     * @return array{todo: int, in_progress: int, done: int, cancelled: int}
     */
    public function listSummary(Group|Organisation $parent, User $viewer): array
    {
        $counts = StaffTask::query()->within($parent)->visibleTo($viewer)->toBase()
            ->selectRaw('staff_tasks.status, count(*) as total')
            ->groupBy('staff_tasks.status')
            ->pluck('total', 'status');

        return collect(StaffTaskStatusEnum::cases())
            ->mapWithKeys(fn (StaffTaskStatusEnum $status) => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            $this->tasksBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $this->tasksRoute('list_all'),
                        'label' => __('All'),
                    ],
                ],
            ]
        );
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromTasksScope($request);

        return $this->handle($this->tasksListParent(), $request->user());
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromTasksScope($request, $organisation);

        return $this->handle($this->tasksListParent(), $request->user());
    }

    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromTasksScope($request, $organisation, $shop);

        return $this->handle($this->tasksListParent(), $request->user());
    }
}
