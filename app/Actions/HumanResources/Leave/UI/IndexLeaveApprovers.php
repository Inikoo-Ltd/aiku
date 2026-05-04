<?php

namespace App\Actions\HumanResources\Leave\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\LeaveApprover;
use App\Models\SysAdmin\Guest;
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
                    ->orWhere('users.contact_name', 'like', '%' . $value . '%');
            });
        });

        $queryBuilder = QueryBuilder::for(LeaveApprover::class)
            ->where('leave_approvers.organisation_id', $organisation->id)
            ->leftJoin('users', 'leave_approvers.user_id', '=', 'users.id');

        return $queryBuilder
            ->defaultSort('leave_approvers.sequence_number')
            ->select([
                'leave_approvers.id',
                'leave_approvers.user_id',
                'leave_approvers.sequence_number',
                'leave_approvers.description',
                'leave_approvers.is_active',
                'users.contact_name as user_contact_name',
                'users.email as user_email',
            ])
            ->allowedSorts(['sequence_number', 'is_active', 'user_contact_name'])
            ->allowedFilters([$globalSearch, 'sequence_number', 'is_active'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
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
                ->column(key: 'user_contact_name', label: __('Approver'), canBeHidden: false, sortable: true)
                ->column(key: 'user_email', label: __('Email'), canBeHidden: false, sortable: true)
                ->column(key: 'organisation', label: __('Organisation'), canBeHidden: false, sortable: true)
                ->column(key: 'sequence_number', label: __('Level'), canBeHidden: false, sortable: true)
                ->column(key: 'description', label: __('Description'), canBeHidden: false, sortable: true)
                ->column(key: 'is_active', label: __('Active'), canBeHidden: true, sortable: true)
                ->column(key: 'action', label: __('Actions'), canBeHidden: false)
                ->defaultSort('sequence_number');
        };
    }

    public function htmlResponse(LengthAwarePaginator $leaveApprovers, ActionRequest $request): Response
    {
        $employeeOptions = Employee::query()
            ->where('organisation_id', $this->parent->id)
            ->whereNotNull('user_id')
            ->orderByRaw('COALESCE(contact_name, alias) asc')
            ->get(['id', 'user_id', 'contact_name', 'alias', 'email'])
            ->map(fn (Employee $employee) => [
                'value' => $employee->user_id,
                'label' => $employee->contact_name ?: $employee->alias ?: __('Employee #:id', ['id' => $employee->id]),
                'email' => $employee->email,
                'employee_id' => $employee->id,
            ]);

        $guestOptions = Guest::query()
            ->where('group_id', $this->parent->group_id)
            ->whereNotNull('user_id')
            ->where('status', true)
            ->orderByRaw('COALESCE(contact_name, company_name, email) asc')
            ->get(['id', 'user_id', 'contact_name', 'company_name', 'email'])
            ->map(fn (Guest $guest) => [
                'value' => $guest->user_id,
                'label' => $guest->contact_name ?: $guest->company_name ?: "Guest #{$guest->id}",
                'email' => $guest->email,
                'employee_id' => null,
            ]);

        $employeeOptions = $employeeOptions
            ->concat($guestOptions)
            ->unique('value')
            ->sortBy('label')
            ->values();

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
