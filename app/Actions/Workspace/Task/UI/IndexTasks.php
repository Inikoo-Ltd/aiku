<?php

namespace App\Actions\Workspace\Task\UI;


use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithWorkspaceAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Group;
use App\Models\Workspace\Task;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use App\Enums\Workspace\TaskStatusEnum;

class IndexTasks extends GrpAction
{
    // use WithWorkspaceAuthorisation;

    public function handle(Group $group, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereAnyWordStartWith('workspace_tasks.title', $value);
        });

        return QueryBuilder::for(Task::class)
            ->where('workspace_tasks.group_id', $group->id)
            ->with(['assigner', 'assignee'])
            ->defaultSort('-workspace_tasks.created_at')
            ->allowedSorts(['title', 'status', 'created_at'])
            ->allowedFilters([$globalSearch, 'status'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $table->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __('No tasks found'),
                        'description' => $this->canEdit ? __('Get started by creating new tasks.') : null
                    ]
                )
                ->column(key: 'title', label: __('Title'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'status', label: __('Status'), canBeHidden: false, sortable: true)
                ->column(key: 'assignee', label: __('Assignee'), canBeHidden: false)
                ->column(key: 'assigner', label: __('Assigned By'), canBeHidden: false)
                ->column(key: 'actions', label: __('Actions'))
                ->defaultSort('-created_at');
        };
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation(app('group'), $request);

        $this->canEdit = $request->user()->authTo('group.webmaster.edit');

        return $this->handle($this->group);
    }

    public function htmlResponse(LengthAwarePaginator $tasks, ActionRequest $request): Response
    {
        $title = __('Tasks');

        $employees = Employee::query()
            ->where('group_id', $this->group?->id)
            ->orderByRaw('COALESCE(contact_name, alias) asc')
            ->get(['id', 'contact_name', 'alias'])
            ->map(fn(Employee $employee) => [
                'value' => $employee->id,
                'label' => $employee->contact_name ?: $employee->alias ?: __('Employee #:id', ['id' => $employee->id]),
            ]);

        return Inertia::render(
            'Workspace/Tasks/Index',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-tasks'],
                        'title' => $title,
                    ],
                    'actions' => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'create',
                            'key'   => 'task',
                            'label' => __('Tabel'),
                            'icon'  => ['fal', 'fa-plus']
                        ] : false,
                    ],
                ],
                'canEdit'   => $this->canEdit,
                'employeeId' => $request->user()->employee($this->group)?->id,
                'data'      => $tasks,
                'employees' => $employees,
                'statuses'   => TaskStatusEnum::labels(),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-tasks',
                        'route' => [
                            'name'       => 'grp.workspace.tasks.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Tasks'),
                    ],
                ],
            ]
        );
    }
}
