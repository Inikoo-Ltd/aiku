<?php

namespace App\Actions\HumanResources\Overtime\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Enums\HumanResources\Overtime\OvertimeRequestStatusEnum;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\OvertimeRequest;
use App\Models\HumanResources\OvertimeType;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOvertime extends OrgAction
{
    use WithHumanResourcesAuthorisation;
    use WithOvertimeSubNavigation;

    public function handle(Organisation $organisation, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('staff.alias', $value)
                    ->orWhereAnyWordStartWith('approver.alias', $value)
                    ->orWhereAnyWordStartWith('overtime_types.name', $value)
                    ->orWhereAnyWordStartWith('overtime_requests.reason', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(OvertimeRequest::class)
            ->where('overtime_requests.organisation_id', $organisation->id)
            ->join('employees as staff', 'staff.id', '=', 'overtime_requests.employee_id')
            ->leftJoin('employees as approver', 'approver.id', '=', 'overtime_requests.approved_by_employee_id')
            ->leftJoin('employees as recorder', 'recorder.id', '=', 'overtime_requests.recorded_by_employee_id')
            ->join('overtime_types', 'overtime_types.id', '=', 'overtime_requests.overtime_type_id')
            ->select([
                'overtime_requests.id',
                'overtime_requests.employee_id',
                'overtime_requests.overtime_type_id',
                'staff.contact_name as employee_name',
                'approver.contact_name as approver_name',
                'overtime_types.name as overtime_type_name',
                'overtime_requests.requested_date',
                'overtime_requests.requested_start_at',
                'overtime_requests.requested_end_at',
                'overtime_requests.requested_duration_minutes',
                'overtime_requests.recorded_start_at',
                'overtime_requests.recorded_end_at',
                'overtime_requests.recorded_duration_minutes',
                'recorder.contact_name as recorder_name',
                'overtime_requests.lieu_requested_minutes',
                'overtime_requests.reason',
                'overtime_requests.status',
                'overtime_requests.approved_at',
                'overtime_requests.rejected_at',
                'overtime_requests.source',
                'overtime_requests.id as options',
            ]);

        return $queryBuilder
            ->defaultSort('-requested_date')
            ->allowedSorts([
                'requested_date',
                'requested_start_at',
                'requested_end_at',
                'requested_duration_minutes',
                'lieu_requested_minutes',
                'status',
            ])
            ->allowedFilters([
                $globalSearch,
                'requested_date',
                'status',
            ])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->column(
                    key: 'requested_date',
                    label: __('Requested'),
                    canBeHidden: false,
                    sortable: true
                )
                ->column(
                    key: 'employee_name',
                    label: __('Staff member'),
                    canBeHidden: false,
                    sortable: true,
                    searchable: true
                )
                ->column(
                    key: 'approver_name',
                    label: __('Approver'),
                    canBeHidden: true,
                    sortable: true,
                    searchable: true
                )
                ->column(
                    key: 'requested_start_at',
                    label: __('From'),
                    canBeHidden: true,
                    sortable: true
                )
                ->column(
                    key: 'recorded_start_at',
                    label: __('Recorded from'),
                    canBeHidden: true,
                    sortable: true
                )
                ->column(
                    key: 'requested_duration_minutes',
                    label: __('Duration'),
                    canBeHidden: true,
                    sortable: true
                )
                ->column(
                    key: 'recorded_duration_minutes',
                    label: __('Recorded duration'),
                    canBeHidden: true,
                    sortable: true
                )
                ->column(
                    key: 'recorder_name',
                    label: __('Recorded by'),
                    canBeHidden: true,
                    sortable: true,
                    searchable: true
                )
                ->column(
                    key: 'lieu_requested_minutes',
                    label: __('Lieu requested'),
                    canBeHidden: true,
                    sortable: true
                )
                ->column(
                    key: 'overtime_type_name',
                    label: __('Overtime type'),
                    canBeHidden: false,
                    sortable: true,
                    searchable: true
                )
                ->column(
                    key: 'status',
                    label: __('Status'),
                    canBeHidden: false,
                    sortable: true
                )
                ->column(
                    key: 'reason',
                    label: __('Reason'),
                    canBeHidden: true,
                    sortable: true
                )
                ->column(
                    key: 'options',
                    label: __('Options'),
                    canBeHidden: false
                )
                ->defaultSort('-requested_date');
        };
    }

    public function htmlResponse(LengthAwarePaginator $overtimeRequests, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/HumanResources/Overtime',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Overtime'),
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-user-hard-hat'],
                        'title' => __('Human resources')
                    ],
                    'iconRight'     => [
                        'icon'  => ['fal', 'fa-clock'],
                        'title' => __('Overtime')
                    ],
                    'title'         => __('Overtime'),
                    'actions'       => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'key'   => 'overtime request',
                            'label' => __('New overtime request'),
                            'icon'  => ['fal', 'fa-plus'],
                        ],
                    ],
                    'subNavigation' => $this->getOvertimeSubNavigation($request),
                ],
                'data'               => $overtimeRequests,
                'employeeOptions'    => $this->organisation->employees()
                    ->orderBy('contact_name')
                    ->where('state', 'working')
                    ->get()
                    ->map(fn (Employee $employee) => [
                        'value' => $employee->id,
                        'label' => $employee->contact_name,
                    ])
                    ->values(),
                'overtimeTypeOptions' => OvertimeType::query()
                    ->where('organisation_id', $this->organisation->id)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get()
                    ->map(fn (OvertimeType $overtimeType) => [
                        'value' => $overtimeType->id,
                        'label' => $overtimeType->name,
                    ])
                    ->values(),
                'statusOptions'      => collect(OvertimeRequestStatusEnum::labels())
                    ->map(fn ($label, $value) => [
                        'value' => $value,
                        'label' => $label,
                    ])
                    ->values(),
            ]
        )->table($this->tableStructure());
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
                        'label' => __('Overtime'),
                        'icon'  => 'fal fa-clock',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.hr.overtime.index' =>
            array_merge(
                ShowHumanResourcesDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb($routeName, $routeParameters)
            ),
            default => [],
        };
    }
}
