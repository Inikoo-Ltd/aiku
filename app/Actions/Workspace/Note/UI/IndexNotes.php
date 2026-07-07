<?php

namespace App\Actions\Workspace\Note\UI;

use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithWorkspaceAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Group;
use App\Models\Workspace\Note;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexNotes extends GrpAction
{
    use WithWorkspaceAuthorisation;

    public function handle(Group $group, Employee $employee, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereAnyWordStartWith('workspace_notes.title', $value);
        });

        return QueryBuilder::for(Note::class)
            ->where('workspace_notes.group_id', $group->id)
            ->where('workspace_notes.employee_id', $employee->id)
            ->defaultSort('-workspace_notes.created_at')
            ->allowedSort(['title', 'created_at'])
            ->allowedFilter([$globalSearch])
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
                ->withEmptyState([
                    'title'         => __('No Notes Found'),
                    'description'   => __('Get started by creating new notes'),
                ])->column(key: 'title', label: __('Title'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'content', label: __('Content'), canBeHidden: false)
                ->column(key: 'actions', label: __('Actions'))
                ->defaultSort('-created_at');
        };
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation(app('group'), $request);

        $employee = $request->user()->employee($this->group);

        return $employee
            ? $this->handle($this->group, $employee)
            : Note::query()->whereRaw('1 = 0')->paginate();
    }

    public function htmlResponse(LengthAwarePaginator $notes, ActionRequest $request): Response
    {
        $title = __('Notes');

        return Inertia::render(
            'Workspace/Notes/Index',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-sticky-note'],
                        'title' => $title,
                    ],
                    'actions' => [
                        'type'  => 'button',
                        'style' => 'create',
                        'key'   => 'note',
                        'label' => __('Note'),
                        'icon'  => ['fal', 'fa-plus'],
                    ],
                ],
                'data' => $notes,
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
                        'icon'  => 'fal fa-sticky-note',
                        'route' => [
                            'name'       => 'grp.workspace.notes.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Notes'),
                    ],
                ],
            ]
        );
    }
}
