<?php

namespace App\Actions\HumanResources\RestrictedPeriods;

use App\Actions\HumanResources\Calendar\WithCalendarSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\LeaveType;
use App\Models\HumanResources\RestrictedPeriod;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRestrictedPeriods extends OrgAction
{
    use WithHumanResourcesAuthorisation;
    use WithCalendarSubNavigation;

    private Organisation $parent;

    public function handle(Organisation $organisation, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('restricted_periods.label', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(RestrictedPeriod::class)
            ->where('restricted_periods.organisation_id', $organisation->id)
            ->with(['targets', 'exceptions']);

        return $queryBuilder
            ->defaultSort('start_date')
            ->select([
                'restricted_periods.id',
                'restricted_periods.label',
                'restricted_periods.start_date',
                'restricted_periods.end_date',
                'restricted_periods.strictness',
                'restricted_periods.is_active',
                'restricted_periods.allow_superuser_override',
            ])
            ->allowedSorts(['label', 'start_date', 'end_date', 'strictness', 'is_active'])
            ->allowedFilters([$globalSearch, 'label', 'strictness', 'is_active'])
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
                ->column(key: 'label', label: __('Label'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'start_date', label: __('Start Date'), canBeHidden: false, sortable: true)
                ->column(key: 'end_date', label: __('End Date'), canBeHidden: false, sortable: true)
                ->column(key: 'strictness', label: __('Strictness'), canBeHidden: true, sortable: true)
                ->column(key: 'is_active', label: __('Active'), canBeHidden: true, sortable: true)
                ->column(key: 'allow_superuser_override', label: __('Superuser Override'), canBeHidden: true, sortable: true)
                ->column(key: 'action', label: __('Actions'), canBeHidden: false)
                ->defaultSort('start_date');
        };
    }

    public function htmlResponse(LengthAwarePaginator $restrictedPeriods, ActionRequest $request): Response
    {
        $employees = Employee::where('organisation_id', $this->parent->id)
            ->where('state', 'working')
            ->select(['id', 'contact_name'])
            ->get()
            ->map(fn ($employee) => [
                'value' => $employee->id,
                'label' => $employee->contact_name,
            ])
            ->values();

        $leaveTypes = LeaveType::where('organisation_id', $this->parent->id)
            ->where('is_active', true)
            ->select(['id', 'name', 'code'])
            ->get()
            ->map(fn ($leaveType) => [
                'value' => $leaveType->id,
                'label' => $leaveType->name,
            ])
            ->values();

        return Inertia::render(
            'Org/HumanResources/RestrictedPeriods',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Restricted periods'),
                'pageHead'    => [
                    'icon'           => ['fal', 'fa-ban'],
                    'title'          => __('Restricted Periods'),
                    'subNavigation'  => $this->getCalendarSubNavigation(),
                    'actions'        => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'key'   => 'restricted period',
                            'label' => __('New Restricted Period'),
                            'icon'  => ['fal', 'fa-plus'],
                        ],
                    ],
                ],
                'data'               => $restrictedPeriods,
                'employees'          => $employees,
                'leaveTypes'         => $leaveTypes,
                'strictnessOptions' => [
                    ['value' => 'block', 'label' => __('Block')],
                    ['value' => 'warn', 'label' => __('Warn')],
                ],
                'targetTypeOptions' => [
                    ['value' => 'Employee', 'label' => __('Employee')],
                    ['value' => 'LeaveType', 'label' => __('Leave Type')],
                ],
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
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Restricted Periods'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.hr.restricted_periods.index' =>
            array_merge(
                ShowHumanResourcesDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb($routeName, $routeParameters)
            ),
            default => [],
        };
    }
}
