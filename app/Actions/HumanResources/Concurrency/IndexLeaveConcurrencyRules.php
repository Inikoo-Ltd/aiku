<?php

namespace App\Actions\HumanResources\Concurrency;

use App\Actions\HumanResources\Leave\UI\WithLeaveSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Enums\HumanResources\Concurrency\LeaveConcurrencyRuleTypeEnum;
use App\Enums\HumanResources\Concurrency\LeaveConcurrencyTargetRoleEnum;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\JobPosition;
use App\Models\HumanResources\LeaveConcurrencyRule;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexLeaveConcurrencyRules extends OrgAction
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
                $query->whereAnyWordStartWith('leave_concurrency_rules.name', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(LeaveConcurrencyRule::class)
            ->where('leave_concurrency_rules.organisation_id', $organisation->id)
            ->with(['targets']);

        return $queryBuilder
            ->defaultSort('name')
            ->select([
                'leave_concurrency_rules.id',
                'leave_concurrency_rules.name',
                'leave_concurrency_rules.rule_type',
                'leave_concurrency_rules.limit',
                'leave_concurrency_rules.max_overlap_days',
                'leave_concurrency_rules.is_active',
            ])
            ->allowedSorts(['name', 'rule_type', 'limit', 'max_overlap_days', 'is_active'])
            ->allowedFilters([$globalSearch, 'name', 'rule_type', 'is_active'])
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
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'rule_type', label: __('Rule Type'), canBeHidden: false, sortable: true)
                ->column(key: 'limit', label: __('Limit'), canBeHidden: true, sortable: true)
                ->column(key: 'max_overlap_days', label: __('Max Overlap Days'), canBeHidden: true, sortable: true)
                ->column(key: 'is_active', label: __('Active'), canBeHidden: true, sortable: true)
                ->column(key: 'action', label: __('Actions'), canBeHidden: false)
                ->defaultSort('name');
        };
    }

    public function htmlResponse(LengthAwarePaginator $leaveConcurrencyRules, ActionRequest $request): Response
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

        $jobPositions = JobPosition::where('organisation_id', $this->parent->id)
            ->select(['id', 'name'])
            ->get()
            ->map(fn ($position) => [
                'value' => $position->id,
                'label' => $position->name,
            ])
            ->values();

        $jobPositionEmployees = Employee::where('organisation_id', $this->parent->id)
            ->where('state', 'working')
            ->with('jobPositions')
            ->get()
            ->flatMap(fn ($employee) => $employee->jobPositions->map(fn ($position) => [
                'jobPositionId' => $position->id,
                'employeeName' => $employee->contact_name,
            ]))
            ->groupBy('jobPositionId')
            ->map(fn ($employees) => $employees->pluck('employeeName')->sort()->values())
            ->toArray();

        return Inertia::render(
            'Org/HumanResources/LeaveConcurrencyRules',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Leave concurrency rules'),
                'pageHead'    => [
                    'icon'           => ['fal', 'fa-project-diagram'],
                    'title'          => __('Leave Concurrency Rules'),
                    'subNavigation'  => $this->getLeaveSubNavigation($request),
                    'actions'        => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'key'   => 'leave concurrency rule',
                            'label' => __('New Leave Concurrency Rule'),
                            'icon'  => ['fal', 'fa-plus'],
                        ],
                    ],
                ],
                'data'                   => $leaveConcurrencyRules,
                'employees'              => $employees,
                'jobPositions'           => $jobPositions,
                'jobPositionEmployees'   => $jobPositionEmployees,
                'ruleTypeOptions'       => collect(LeaveConcurrencyRuleTypeEnum::cases())
                    ->map(fn ($case) => [
                        'value' => $case->value,
                        'label' => $case->label(),
                    ])
                    ->values(),
                'targetTypeOptions' => [
                    ['value' => 'Employee', 'label' => __('Employee')],
                    ['value' => 'JobPosition', 'label' => __('Job Position')],
                ],
                'roleOptions'       => collect(LeaveConcurrencyTargetRoleEnum::cases())
                    ->map(fn ($case) => [
                        'value' => $case->value,
                        'label' => $case->label(),
                    ])
                    ->values(),
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
                        'label' => __('Leave Concurrency Rules'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.hr.leave_concurrency_rules.index' =>
            array_merge(
                ShowHumanResourcesDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb($routeName, $routeParameters)
            ),
            default => [],
        };
    }
}
