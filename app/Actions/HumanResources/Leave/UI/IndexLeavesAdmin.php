<?php

namespace App\Actions\HumanResources\Leave\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesSectionAuthorisation;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Models\HumanResources\Employee;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Http\Resources\HumanResources\LeaveResource;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Holiday;
use App\Models\HumanResources\JobPosition;
use App\Models\HumanResources\Leave;
use App\Models\HumanResources\LeaveApprover;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\HumanResources\LeaveTypeResolver;

class IndexLeavesAdmin extends OrgAction
{
    use WithLeaveSubNavigation;
    use WithHumanResourcesSectionAuthorisation {
        authorize as authorizeHumanResourcesSection;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->isActiveLeaveApprover($request)) {
            return true;
        }

        return $this->authorizeHumanResourcesSection($request);
    }

    private function isActiveLeaveApprover(ActionRequest $request): bool
    {
        return LeaveApprover::query()
            ->where('organisation_id', $this->organisation->id)
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->exists();
    }

    public function handle(Organisation $organisation, ?string $prefix = null): LengthAwarePaginator
    {
        $prefix = $prefix ?? 'leaves';

        InertiaTable::updateQueryBuilderParameters($prefix);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('employee_name', 'like', "%$value%")
                    ->orWhere('reason', 'like', "%$value%");
            });
        });

        $statusFilter = AllowedFilter::callback('status', function ($query, $value) {
            $query->where('status', $value);
        });

        $typeFilter = AllowedFilter::callback('type', function ($query, $value) {
            $query->where('type', $value);
        });

        $queryBuilder = QueryBuilder::for(Leave::class)
            ->where('organisation_id', $organisation->id)
            ->when($this->isRestrictedToSection(), fn ($query) => $query->whereIn('employee_id', $this->sectionEmployeeIds))
            ->with(['employee', 'leaveType'])
            ->allowedFilters([$globalSearch, $statusFilter, $typeFilter])
            ->allowedSorts(['start_date', 'end_date', 'created_at', 'employee_name'])
            ->defaultSort('-created_at');

        return $queryBuilder->paginate(request()->input($prefix . 'perPage', 15))
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $leaves): AnonymousResourceCollection
    {
        return LeaveResource::collection($leaves);
    }

    public function htmlResponse(LengthAwarePaginator $leaves, ActionRequest $request): Response
    {
        $employees = Employee::where('organisation_id', $this->organisation->id)
            ->where('state', EmployeeStateEnum::WORKING)
            ->when($this->isRestrictedToSection(), fn ($query) => $query->whereIn('id', $this->sectionEmployeeIds))
            ->with([
                'jobPositions:job_positions.id,job_positions.name,job_positions.department',
                'users' => fn ($query) => $query->wherePivot('status', true)->where('users.status', true),
            ])
            ->orderBy('contact_name')
            ->get(['id', 'contact_name']);

        return Inertia::render(
            'Org/HumanResources/LeaveAdmin',
            [
                'title' => __('Leave Requests'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-calendar-minus'],
                        'title' => __('Human resources')
                    ],
                    'iconRight'     => [
                        'icon'  => ['fal', 'fa-calendar-minus'],
                        'title' => __('Leave')
                    ],
                    'title'         => __('Leave Requests'),
                    'subNavigation' => $this->isRestrictedToSection() ? [] : $this->getLeaveSubNavigation($request),
                ],
                'leaves' => LeaveResource::collection($leaves),
                'type_options' => LeaveTypeResolver::optionsForOrganisation($this->organisation->id, false, $this->organisation->country?->code),
                'status_options' => LeaveStatusEnum::labels(),
                'can_edit_admin' => Auth::check() && LeaveApprover::query()
                    ->where('organisation_id', $this->organisation->id)
                    ->where('user_id', Auth::id())
                    ->where('is_active', true)
                    ->exists(),
                'holidays' => $this->getHolidayDates(),
                'can_record' => $request->user()->authTo(["human-resources.{$this->organisation->id}.edit", "org-supervisor.{$this->organisation->id}.human-resources"]),
                'employee_options' => $employees->pluck('contact_name', 'id'),
                'employee_job_positions' => $employees->mapWithKeys(fn (Employee $employee) => [
                    $employee->id => $employee->jobPositions->map(fn (JobPosition $jobPosition) => $jobPosition->only(['id', 'name', 'department'])),
                ]),
                // ponytail: a few permission queries per employee, fine at tens of employees; precompute per user if an organisation grows to hundreds
                'employee_ids_with_group_access' => $employees
                    ->filter(fn (Employee $employee) => $employee->users->contains(fn (User $user) => $user->hasGroupAccess()))
                    ->pluck('id')
                    ->values(),
            ]
        )->table($this->tableStructure());
    }

    private function getHolidayDates(): array
    {
        $now = Carbon::now();
        $holidays = Holiday::query()
            ->where('organisation_id', $this->organisation->id)
            ->where('to', '>=', $now->copy()->startOfYear())
            ->where('from', '<=', $now->copy()->addYear()->endOfYear())
            ->get();

        $dates = [];

        foreach ($holidays as $holiday) {
            $current = $holiday->from->copy();
            while ($current->lte($holiday->to)) {
                $dateKey = $current->format('Y-m-d');
                if (!isset($dates[$dateKey])) {
                    $dates[$dateKey] = ['date' => $dateKey, 'labels' => []];
                }
                if ($holiday->label) {
                    $dates[$dateKey]['labels'][] = $holiday->label;
                }
                $current->addDay();
            }
        }

        return array_values(array_map(
            fn (array $item): array => [
                'date'  => $item['date'],
                'label' => implode(', ', $item['labels']),
            ],
            $dates
        ));
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->column(
                    key: 'employee_name',
                    label: __('Employee'),
                    canBeHidden: false,
                    sortable: true,
                    searchable: true
                )
                ->column(
                    key: 'type_label',
                    label: __('Type'),
                    canBeHidden: false
                )
                ->column(
                    key: 'start_date',
                    label: __('Start Date'),
                    canBeHidden: false,
                    sortable: true
                )
                ->column(
                    key: 'end_date',
                    label: __('End Date'),
                    canBeHidden: true,
                    sortable: true
                )
                ->column(
                    key: 'duration_days',
                    label: __('Days'),
                    canBeHidden: true,
                    sortable: true
                )
                ->column(
                    key: 'status',
                    label: __('Status'),
                    canBeHidden: false,
                    sortable: true
                )
                ->column(
                    key: 'approval_progress',
                    label: __('Approval'),
                    canBeHidden: false
                )
                ->column(
                    key: 'reason',
                    label: __('Reason'),
                    canBeHidden: true,
                    sortable: true
                )
                ->column(
                    key: 'attachments',
                    label: __('Attachments'),
                    canBeHidden: false
                )
                ->column(
                    key: 'actions',
                    label: __('Options'),
                    canBeHidden: false
                )
                ->defaultSort('-created_at');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (string $routeName, array $routeParameters) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Leave Requests'),
                        'icon'  => 'fal fa-calendar-minus',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.hr.leaves.index' =>
            array_merge(
                ShowHumanResourcesDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb($routeName, $routeParameters)
            ),
            default => [],
        };
    }
}
