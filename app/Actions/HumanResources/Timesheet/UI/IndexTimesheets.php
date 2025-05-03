<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Apr 2024 09:57:32 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Timesheet\UI;

use App\Actions\HumanResources\Employee\UI\ShowEmployee;
use App\Actions\HumanResources\WithEmployeeSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Enums\Helpers\Period\PeriodEnum;
use App\Enums\UI\HumanResources\TimesheetsTabsEnum;
use App\Http\Resources\HumanResources\TimesheetsResource;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Timesheet;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexTimesheets extends OrgAction
{
    use WithEmployeeSubNavigation;
    use WithHumanResourcesAuthorisation;

    private Group|Employee|Organisation|Guest $parent;

    public function handle(Group|Organisation|Employee|Guest $parent, ?string $prefix = null, bool $isTodayTimesheet = false): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('timesheets.subject_name', $value);
            });
        });

        $query = QueryBuilder::for(Timesheet::class);

        if ($parent instanceof Organisation) {
            $query->where('timesheets.organisation_id', $parent->id);
        } elseif ($parent instanceof Employee) {
            $query->where('timesheets.subject_type', 'Employee')
                ->where('timesheets.subject_id', $parent->id);
        } elseif ($parent instanceof Group) {
            $query->where('timesheets.group_id', $parent->id);
        } else {
            $query->where('subject_type', 'Guest')->where('subject_id', $parent->id);
        }
        $query->leftjoin('organisations', 'timesheets.organisation_id', '=', 'organisations.id');

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        if ($isTodayTimesheet) {
            $query->whereDate('timesheets.date', now()->format('Y-m-d'));
        }

        $query->withFilterPeriod('date');
        $query->select([
            'timesheets.id',
            'timesheets.date',
            'timesheets.subject_name',
            'timesheets.start_at',
            'timesheets.end_at',
            'timesheets.working_duration',
            'timesheets.breaks_duration',
            'timesheets.number_time_trackers',
            'timesheets.number_open_time_trackers',
            'organisations.name as organisation_name',
            'organisations.slug as organisation_slug',
        ]);
        return $query
            ->defaultSort('date')
            ->allowedSorts(['date', 'subject_name', 'working_duration', 'breaks_duration'])
            ->allowedFilters([$globalSearch, 'subject_name'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    protected function getPeriodFilters(): array
    {
        $elements = array_merge_recursive(
            PeriodEnum::labels(),
            PeriodEnum::date()
        );

        return [
            'period' => [
                'label'    => __('Period'),
                'elements' => $elements
            ],
        ];
    }

    public function tableStructure(Group|Organisation|Employee|Guest $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $noResults = __("No timesheets found");
            if ($parent instanceof Employee || $parent instanceof Guest) {
                $stats     = $parent->stats;
                $noResults = __("Employee has no timesheets");
            } else {
                $stats = $parent->humanResourcesStats;
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => $noResults,
                        'count' => $stats->number_timesheets
                    ]
                )
                ->withModelOperations($modelOperations)
                ->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true);

            if ($parent instanceof Organisation) {
                $table->column(key: 'subject_name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
            }

            foreach ($this->getPeriodFilters() as $periodFilter) {
                $table->periodFilters($periodFilter['elements']);
            }

            $table->column(key: 'working_duration', label: __('working'), canBeHidden: false, sortable: true)
                ->column(key: 'breaks_duration', label: __('breaks'), canBeHidden: false, sortable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, searchable: true);
            }
            $table->defaultSort('date');
        };
    }


    public function jsonResponse(LengthAwarePaginator $timesheets): AnonymousResourceCollection
    {
        return TimesheetsResource::collection($timesheets);
    }

    public function htmlResponse(LengthAwarePaginator $timesheets, ActionRequest $request): Response
    {
        $subNavigation = [];
        $model         = '';
        $title         = __('Timesheets');
        $icon          = [
            'title' => __('Timesheets'),
            'icon'  => 'fal fa-stopwatch'
        ];
        $afterTitle    = null;
        $iconRight     = null;
        $modelOperations = [

            'createLink' => [
                [
                    'route' => [
                        'name'       => 'grp.org.hr.timesheets.index',
                        'parameters' => array_values($request->route()->originalParameters())
                    ],
                    'label' => __('per employee')
                ]
            ]

        ];
        if ($this->parent instanceof Group) {
            $modelOperations = [];
        }
        if ($this->parent instanceof Employee) {
            $afterTitle    = [
                'label' => $title
            ];
            $iconRight     = $icon;
            $subNavigation = $this->getEmployeeSubNavigation($this->parent, $request);
            $title         = $this->parent->contact_name;

            $icon = [
                'icon'  => ['fal', 'fa-user-hard-hat'],
                'title' => __('employee')
            ];
        }

        return Inertia::render(
            'Org/HumanResources/Timesheets',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->parent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('timesheets'),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                    'actions' => [
                        class_basename($this->parent) !== class_basename(Organisation::class) ? [
                            'type'   => 'button',
                            'style'  => 'tertiary',
                            'label'  => 'PDF',
                            'target' => '_blank',
                            'icon'   => 'fal fa-file-pdf',
                            'key'    => 'action',
                            'route'  => [
                                'name'       => match (class_basename($this->parent)) {
                                    class_basename(Organisation::class) => 'grp.org.hr.timesheets.export',
                                    class_basename(Employee::class) => 'grp.org.hr.employees.show.timesheets.pdf',
                                },
                                'parameters' => match (class_basename($this->parent)) {
                                    class_basename(Organisation::class) => [
                                        'organisation' => $this->parent->slug,
                                        ...$request->query
                                    ],
                                    class_basename(Employee::class) => [
                                        'organisation' => $this->parent->organisation->slug,
                                        'employee' => $this->parent->slug,
                                        ...$request->query
                                    ],
                                },
                            ]
                        ] : []
                    ]
        ],

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => TimesheetsTabsEnum::navigation()
                ],

                'data' => TimesheetsResource::collection($timesheets)

            ]
        )->table(
            $this->tableStructure($this->parent, modelOperations: $modelOperations)
        );
    }


    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function inEmployee(Organisation $organisation, Employee $employee, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $employee;
        $this->initialisation($organisation, $request);

        return $this->handle($employee);
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(group());
    }


    public function getBreadcrumbs(Group|Organisation|Employee|Guest $parent, string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Timesheets'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.hr.timesheets.index' => array_merge(
                ShowHumanResourcesDashboard::make()->getBreadcrumbs(Arr::only($routeParameters, 'organisation')),
                $headCrumb(
                    [
                        'name'       => 'grp.org.hr.timesheets.index',
                        'parameters' => Arr::only($routeParameters, 'organisation')
                    ]
                )
            ),
            'grp.org.hr.employees.show.timesheets.index' => array_merge(
                ShowEmployee::make()->getBreadcrumbs($parent, $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.hr.employees.show.timesheets.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.overview.hr.timesheets.index' => array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => 'grp.overview.hr.timesheets.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
        };
    }
}
