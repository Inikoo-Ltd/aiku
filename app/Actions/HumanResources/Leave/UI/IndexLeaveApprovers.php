<?php

namespace App\Actions\HumanResources\Leave\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\LeaveApprover;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexLeaveApprovers extends OrgAction
{
    use WithHumanResourcesAuthorisation;
    use WithLeaveSubNavigation;

    private Organisation $parent;

    public function handle(Organisation $organisation, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('users.email', 'like', '%' . $value . '%')
                    ->orWhere('users.contact_name', 'like', '%' . $value . '%')
                    ->orWhere('organisations.name', 'like', '%' . $value . '%');
            });
        });

        $queryBuilder = QueryBuilder::for(LeaveApprover::class)
            ->whereIn('leave_approvers.organisation_id', $organisation->group->organisations()->pluck('id'))
            ->leftJoin('users', 'leave_approvers.user_id', '=', 'users.id')
            ->leftJoin('organisations', 'leave_approvers.organisation_id', '=', 'organisations.id');

        return $queryBuilder
            ->defaultSort('leave_approvers.sequence_number')
            ->select([
                'leave_approvers.user_id',
                'leave_approvers.sequence_number',
                'leave_approvers.description',
                'leave_approvers.is_active',
                'users.contact_name as user_contact_name',
                'users.email as user_email',
            ])
            ->selectRaw('MIN(leave_approvers.id) as id')
            ->selectRaw("string_agg(DISTINCT organisations.name, ', ' ORDER BY organisations.name) as organisation_names")
            ->selectRaw("string_agg(DISTINCT leave_approvers.organisation_id::text, ',' ORDER BY leave_approvers.organisation_id::text) as organisation_ids")
            ->selectRaw("string_agg(DISTINCT leave_approvers.id::text, ',' ORDER BY leave_approvers.id::text) as leave_approver_ids")
            ->groupBy([
                'leave_approvers.user_id',
                'leave_approvers.sequence_number',
                'leave_approvers.description',
                'leave_approvers.is_active',
                'users.contact_name',
                'users.email',
            ])
            ->allowedSorts(['sequence_number', 'is_active', 'user_contact_name', 'user_email'])
            ->allowedFilters([$globalSearch, 'sequence_number', 'is_active'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString()
            ->through(function ($item) {
                $item->organisation_ids = $item->organisation_ids
                    ? array_map('intval', explode(',', $item->organisation_ids))
                    : [];

                $item->leave_approver_ids = $item->leave_approver_ids
                    ? array_map('intval', explode(',', $item->leave_approver_ids))
                    : [];

                return $item;
            });
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->column(key: 'organisation_names', label: __('Organisations'), canBeHidden: false)
                ->column(key: 'user_contact_name', label: __('Approver'), canBeHidden: false, sortable: true)
                ->column(key: 'user_email', label: __('Email'), canBeHidden: false, sortable: true)
                ->column(key: 'sequence_number', label: __('Level'), canBeHidden: false, sortable: true)
                ->column(key: 'description', label: __('Description'), canBeHidden: false, sortable: true)
                ->column(key: 'is_active', label: __('Active'), canBeHidden: true, sortable: true)
                ->column(key: 'action', label: __('Actions'), canBeHidden: false)
                ->defaultSort('sequence_number');
        };
    }

    public function htmlResponse(LengthAwarePaginator $leaveApprovers, ActionRequest $request): Response
    {
        $organisationIds = $this->parent->group->organisations()->pluck('id');

        $employeeOptions = Employee::query()
            ->whereIn('employees.organisation_id', $organisationIds)
            ->leftJoin('user_has_models as active_employee_users', function ($join) {
                $join->on('active_employee_users.model_id', '=', 'employees.id')
                    ->where('active_employee_users.model_type', '=', 'Employee')
                    ->where('active_employee_users.status', '=', true);
            })
            ->leftJoin('organisations', 'organisations.id', '=', 'employees.organisation_id')
            ->select([
                'employees.id',
                'employees.user_id',
                'employees.contact_name',
                'employees.alias',
                'employees.email',
                'employees.organisation_id',
                'organisations.name as organisation_name',
                'active_employee_users.user_id as active_user_id',
            ])
            ->get()
            ->map(function (Employee $employee) {
                $userId = $employee->active_user_id ?: $employee->user_id;

                return [
                    'value' => $userId,
                    'label' => ($employee->contact_name ?: $employee->alias ?: __('Employee #:id', ['id' => $employee->id])) . ' (' . $employee->organisation_name . ')',
                    'email' => $employee->email,
                    'employee_id' => $employee->id,
                    'organisation_id' => $employee->organisation_id,
                ];
            })
            ->filter(fn (array $employee) => !empty($employee['value']))
            ->unique('value')
            ->values();

        $organisationOptions = $this->parent->group->organisations()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Organisation $org) => [
                'value' => $org->id,
                'label' => $org->name,
            ]);

        return Inertia::render(
            'Org/HumanResources/LeaveApprovers',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title' => __('Leave Approvers'),
                'pageHead' => [
                    'icon' => ['fal', 'fa-user-shield'],
                    'title' => __('Leave Approvers'),
                    'actions' => [
                        [
                            'type' => 'button',
                            'style' => 'create',
                            'key' => 'leave approver',
                            'label' => __('New Leave Approver'),
                            'icon' => ['fal', 'fa-plus'],
                        ],
                    ],
                    'subNavigation' => $this->getLeaveSubNavigation($request),
                ],
                'data' => $leaveApprovers,
                'employeeOptions' => $employeeOptions,
                'organisationOptions' => $organisationOptions,
                'organisationId' => $this->parent->id,
            ]
        )->table($this->tableStructure());
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (string $routeName, array $routeParameters) {
            return [
                [
                    'type' => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => $routeName,
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Leave Approvers'),
                        'icon' => 'fal fa-user-shield',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.hr.leave_approvers.index' =>
            array_merge(
                ShowHumanResourcesDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb($routeName, $routeParameters)
            ),
            default => [],
        };
    }
}
