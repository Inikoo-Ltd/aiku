<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Mar 2023 19:12:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Employee\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Employee\EmployeeTypeEnum;
use App\Http\Resources\HumanResources\EmployeesResource;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexEmployees extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    private Organisation|JobPosition|Group $parent;

    protected function getElementGroups(Organisation|JobPosition|Group $parent): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    EmployeeStateEnum::labels(),
                    EmployeeStateEnum::count($parent)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('state', $elements);
                }

            ],
            'type'  => [
                'label'    => __('Type'),
                'elements' => EmployeeTypeEnum::labels(),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('type', $elements);
                }
            ],
        ];
    }

    public function handle(Organisation|JobPosition|Group $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('employees.contact_name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        $queryBuilder = QueryBuilder::for(Employee::class);

        if (class_basename($parent) == 'Organisation') {
            $queryBuilder->where('organisation_id', $parent->id);
        } elseif (class_basename($parent) == 'JobPosition') {
            $queryBuilder->leftJoin('employee_has_job_positions', 'employee_has_job_positions.employee_id', 'employees.id');
            $queryBuilder->where('employee_has_job_positions.job_position_id', $parent->id);
            $queryBuilder->where('employees.organisation_id', $parent->organisation_id);
        } else {
            $queryBuilder->where('employees.group_id', $parent->id);
        }
        $queryBuilder->leftjoin('organisations', 'employees.organisation_id', '=', 'organisations.id');

        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        $queryBuilder->select(['employees.slug', 'employees.job_title', 'employees.contact_name', 'employees.state', 'organisations.name as organisation_name', 'organisations.slug as organisation_slug',]);

        if (class_basename($parent) == 'Organisation') {
            $jobPositions = DB::table('employee_has_job_positions')
                ->select(
                    'employee_id',
                    DB::raw('jsonb_agg(json_build_object(\'name\',job_positions.name,\'slug\',job_positions.slug)) as job_positions')
                )
                ->leftJoin('job_positions', 'employee_has_job_positions.job_position_id', 'job_positions.id')
                ->groupBy('employee_id');
            $queryBuilder->leftJoinSub($jobPositions, 'job_positions', function (JoinClause $join) {
                $join->on('employees.id', '=', 'job_positions.employee_id');
            });
            $queryBuilder->addSelect('job_positions');
        }


        return $queryBuilder
            ->defaultSort('slug')
            // ->withBetweenDates(['employment_start_at', 'created_at', 'updated_at'])
            ->allowedSorts(['slug', 'state', 'contact_name', 'job_title', 'worker_number'])
            ->allowedFilters([$globalSearch, 'slug', 'contact_name', 'state'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Organisation|JobPosition|Group $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch();

            if (class_basename($parent) == 'Organisation') {

                $table->withEmptyState(
                    [
                        'title'       => __('no employees'),
                        'description' => $this->canEdit ? __('Get started by creating a new employee.') : null,
                        'count'       => $parent->humanResourcesStats->number_employees,
                        'action'      => $this->canEdit ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('new employee'),
                            'label'   => __('employee'),
                            'route'   => [
                                'name'       => 'grp.org.hr.employees.create',
                                'parameters' => [
                                    'organisation' => $this->parent->slug
                                ]
                            ]
                        ] : null
                    ]
                );
            }
            $table->withLabelRecord([__('Employee'),__('Employees')]);
            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'slug', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'contact_name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'job_title', label: __('job title'), canBeHidden: false);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, searchable: true);
            }
            if (class_basename($parent) == 'Organisation') {
                $table->column(key: 'positions', label: __('responsibilities'), canBeHidden: false);
            }
            $table->defaultSort('slug');
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        if (class_basename($this->parent) == 'Organisation') {
            $this->canEdit = $request->user()->authTo("human-resources.{$this->organisation->id}.edit");

            return $request->user()->authTo("human-resources.{$this->organisation->id}.view");
        } elseif (class_basename($this->parent) == 'Group') {
            return $request->user()->authTo("group-overview");
        } else {
            return $request->user()->authTo('group-reports');
        }
    }


    public function jsonResponse(LengthAwarePaginator $employees): AnonymousResourceCollection
    {
        return EmployeesResource::collection($employees);
    }


    public function htmlResponse(LengthAwarePaginator $employees, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/HumanResources/Employees',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('employees'),
                'pageHead'    => [
                    'icon'    => [
                        'title' => __('Employee'),
                        'icon'  => 'fal fa-user-hard-hat'
                    ],
                    'model'   => __('Human Resources'),
                    'title'   => __('employees'),
                    'actions' =>
                        $this->canEdit ? [
                            [
                                'key'   => 'btn_upload',
                                'type'  => 'button',
                                'style' => 'secondary',
                                'label' => __('Upload'),
                                'icon'    => ['fal', 'fa-upload'],
                            ],
                            [
                                'type'  => 'button',
                                'style' => 'create',
                                'label' => __('employee'),
                                'route' => [
                                    'name'       => 'grp.org.hr.employees.create',
                                    'parameters' => $request->route()->originalParameters()
                                ]
                            ],
                        ]
                        : false
                ],
                'upload_spreadsheet' => [
                    'event'             => 'action-progress',
                    'channel'           => 'grp.personal.' . $request->user()->id,
                    'required_fields'   => ['worker_number', 'alias', 'job_title', 'positions', 'starting_date', 'state'],
                    'template'          => [
                        'label' => 'Download template (.xlsx)',
                    ],
                    'route' => [
                        'upload'  => [
                            'name'  => 'grp.models.employees.import',
                            'parameters' => [
                                'organisation' => $this->parent->id,
                            ],
                            'method' => 'post'
                        ],
                        'history'  => [
                            'name'  => 'grp.org.hr.employees.history-uploads',
                            'parameters' => [
                                'organisation' => $this->parent->slug,
                            ],
                            'method' => 'get'
                        ],
                        'download' => [
                            'name'  => 'grp.org.hr.employees.uploads.templates',
                            'parameters' => [
                                'organisation' => $this->parent->slug
                            ]
                        ]
                    ],
                ],
                'data'        => EmployeesResource::collection($employees),
            ]
        )->table($this->tableStructure($this->parent));
    }


    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(group());
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
                            'parameters' => array_merge(
                                [
                                    '_query' => [
                                        'elements[state]' => 'working'
                                    ]
                                ],
                                $routeParameters
                            )
                        ],
                        'label' => __('Employees'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.hr.employees.index' =>
            array_merge(
                ShowHumanResourcesDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb($routeName, $routeParameters)
            ),
            'grp.overview.hr.employees.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb($routeName, $routeParameters)
            ),

        };
    }
}
