<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 Jun 2023 09:33:11 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\JobPosition\UI;

use App\Actions\HumanResources\Employee\UI\ShowEmployee;
use App\Actions\HumanResources\WithEmployeeSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Http\Resources\HumanResources\JobPositionsResource;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexJobPositions extends OrgAction
{
    use WithEmployeeSubNavigation;
    use WithHumanResourcesAuthorisation;

    private Group|Employee|Organisation $parent;

    public function handle(Group|Organisation|Employee $parent, string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('job_positions.name', $value)
                    ->orWhereStartWith('job_positions.code', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(JobPosition::class);
        $queryBuilder->leftJoin('job_position_stats', 'job_positions.id', 'job_position_stats.job_position_id');
        if ($parent instanceof Organisation) {

            $queryBuilder->where(function (Builder $query) use ($parent) {
                $query->where('organisation_id', $parent->id)->orWhere('organisation_id', null);
            });


        } elseif ($parent instanceof Group) {
            $queryBuilder->where('job_positions.group_id', $parent->id);
        } else {
            $queryBuilder->leftJoin('employee_has_job_positions', 'job_positions.id', 'employee_has_job_positions.job_position_id');
            $queryBuilder->where('employee_id', $parent->id);
            $queryBuilder->addSelect('employee_has_job_positions.share');
        }
        $queryBuilder->leftjoin('organisations', 'job_positions.organisation_id', '=', 'organisations.id');
        $queryBuilder->select(['job_positions.code', 'job_positions.slug', 'job_positions.name', 'job_position_stats.number_employees_currently_working', 'organisations.name as organisation_name', 'organisations.slug as organisation_slug']);

        return $queryBuilder
            ->select(['job_positions.code as code', 'job_positions.slug as slug', 'job_positions.name as name', 'job_position_stats.number_employees_currently_working', 'organisations.name as organisation_name', 'organisations.slug as organisation_slug'])
            ->defaultSort('code')
            ->allowedSorts(['code', 'name', 'number_employees_currently_working', 'share'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $jobPositions): AnonymousResourceCollection
    {
        return JobPositionsResource::collection($jobPositions);
    }

    public function tableStructure(Group|Organisation|Employee $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table->withEmptyState(
                match (class_basename($parent)) {
                    'Organisation' => [
                        'title' => __("No responsibilities found"),
                        'count' => $parent->humanResourcesStats->number_job_positions,
                    ],
                    'Employee' => [
                        'title' => __("Employee has no responsibilities"),
                        'count' => $parent->stats->number_job_positions

                    ],
                    default => null
                }
            );

            $table->withLabelRecord([__('responsibility'), __('responsibilities')]);
            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);

            if ($parent instanceof Organisation) {
                $table->column(key: 'number_employees_currently_working', label: __('employees'), canBeHidden: false, sortable: true, searchable: true);
            } else {
                $table->column(key: 'share', label: __('Share'), canBeHidden: false, sortable: true, searchable: true);
            }
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, searchable: true);
            }
            $table->defaultSort('code');
        };
    }

    public function htmlResponse(LengthAwarePaginator $jobPositions, ActionRequest $request): Response
    {
        $subNavigation = [];
        $model         = '';
        $title         = __('Responsibilities');
        $icon          = [
            'title' => __('Responsibilities'),
            'icon'  => 'fal fa-clipboard-list-check'
        ];
        $afterTitle    = null;
        $iconRight     = null;

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
            'Org/HumanResources/JobPositions',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->parent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Responsibilities'),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                ],
                'data'        => JobPositionsResource::collection($jobPositions),


            ]
        )->table($this->tableStructure($this->parent));
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

    public function getBreadcrumbs(Group|Organisation|Employee $parent, string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Responsibilities'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.hr.job_positions.index' => array_merge(
                ShowHumanResourcesDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.hr.job_positions.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.org.hr.employees.show.positions.index' => array_merge(
                ShowEmployee::make()->getBreadcrumbs($parent, $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.hr.employees.show.positions.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.overview.hr.responsibilities.index' => array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            ),
        };
    }
}
