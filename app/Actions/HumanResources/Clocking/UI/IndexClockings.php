<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 11:34:32 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\HumanResources\Clocking\UI;

use App\Actions\HumanResources\ClockingMachine\UI\ShowClockingMachine;
use App\Actions\HumanResources\Workplace\UI\ShowWorkplace;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Http\Resources\HumanResources\ClockingsResource;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\Timesheet;
use App\Models\HumanResources\Workplace;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\InertiaTable\InertiaTable;
use App\Services\QueryBuilder;

class IndexClockings extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    private Organisation|Workplace|ClockingMachine|Timesheet $parent;

    public function handle(Organisation|Workplace|ClockingMachine|Timesheet $parent, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }
        $queryBuilder = QueryBuilder::for(Clocking::class);

        switch (class_basename($parent)) {
            case 'ClockingMachine':
                $queryBuilder->where('clockings.clocking_machine_id', $parent->id);
                break;
            case 'Workplace':
                $queryBuilder->where('clockings.workplace_id', $parent->id);
                break;
            case 'Organisation':
                $queryBuilder->where('clockings.organisation_id', $parent->id);
                break;
            case 'Timesheet':
                $queryBuilder->where('clockings.timesheet_id', $parent->id);
                break;
            default:
                abort(404, 'Not Found');
        }

        return $queryBuilder
            ->defaultSort('clockings.clocked_at')
            ->select(
                [
                    'clocked_at',
                    'clockings.id',
                    'clockings.type',
                    'workplaces.slug as workplace_slug',
                    'clocking_machines.slug as clocking_machine_slug',
                    'clocking_machine_id',
                    'media.slug as media_slug',
                    'media.id as media_id'
                ]
            )
            ->leftJoin('workplaces', 'clockings.workplace_id', 'workplaces.id')
            ->leftJoin('media', 'clockings.image_id', 'media.id')
            ->leftJoin('clocking_machines', 'clockings.clocking_machine_id', 'clocking_machines.id')
            ->allowedSorts(['clocked_at'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }


    public function tableStructure(Organisation|Workplace|ClockingMachine|Timesheet $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'title'       => __('no clockings'),
                        'description' => $this->canEdit ? __('Get started by creating a new clocking.') : null,
                        'count'       =>
                            class_basename($parent) == 'ClockingMachine' ? $parent->humanResourcesStats?->number_clockings : $parent->stats?->number_clockings,
                    ]
                )
                ->column(key: 'media_slug', label: __('photo'), canBeHidden: false)
                ->column(key: 'clocked_at', label: __('time'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('slug');
        };
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle(parent: $organisation);
    }

    public function inWorkplace(Organisation $organisation, Workplace $workplace, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $workplace;
        $this->initialisation($organisation, $request);

        return $this->handle(parent: $workplace);
    }

    public function inClockingMachine(Organisation $organisation, ClockingMachine $clockingMachine, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $clockingMachine;
        $this->initialisation($organisation, $request);

        return $this->handle(parent: $clockingMachine);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inWorkplaceInClockingMachine(Organisation $organisation, Workplace $workplace, ClockingMachine $clockingMachine, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $clockingMachine;
        $this->initialisation($organisation, $request);

        return $this->handle(parent: $clockingMachine);
    }


    public function jsonResponse(LengthAwarePaginator $clockings): AnonymousResourceCollection
    {
        return ClockingsResource::collection($clockings);
    }


    public function htmlResponse(LengthAwarePaginator $clockings, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/HumanResources/Clockings',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Clockings'),
                'pageHead'    => [
                    'title'   => __('clockings'),
                    'actions' => [
                        $this->canEdit
                        && (
                            $request->route()->getName() == 'grp.org.hr.workplaces.show.clockings.index' || $request->route()->getName() == 'grp.org.hr.workplaces.show.clocking_machines.show.clockings.index'
                        )
                            ? [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('clockings'),
                            'route' =>
                                match ($request->route()->getName()) {
                                    'grp.org.hr.workplaces.show.clockings.index' => [
                                        'name'       => 'grp.org.hr.workplaces.show.clockings.create',
                                        'parameters' => $request->route()->originalParameters()
                                    ],
                                    default => [
                                        'name'       => 'grp.org.hr.workplaces.show.clocking_machines.show.clockings.create',
                                        'parameters' => $request->route()->originalParameters()
                                    ]
                                }
                        ] : false
                    ]
                ],
                'data'        => ClockingsResource::collection($clockings),


            ]
        )->table($this->tableStructure($this->parent));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('clockings'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.hr.clockings.index' =>
            array_merge(
                (new ShowHumanResourcesDashboard())->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name' => 'grp.org.hr.clockings.index',
                        null
                    ]
                )
            ),
            'grp.org.hr.workplaces.show.clockings.index' =>
            array_merge(
                ShowWorkplace::make()->getBreadcrumbs($routeParameters),
                $headCrumb([
                    'name'       => $routeName,
                    'parameters' => $routeParameters
                ])
            ),
            'grp.org.hr.clocking_machines.show.clockings.index' =>
            array_merge(
                (new ShowClockingMachine())->getBreadcrumbs(
                    'grp.org.hr.clocking_machines.show',
                    [
                        'clockingMachine' => $routeParameters['clockingMachine']
                    ]
                ),
                $headCrumb([
                    'name'       => 'grp.org.hr.clocking_machines.show.clockings.index',
                    'parameters' =>
                        [
                            $routeParameters['clockingMachine']->slug
                        ]
                ])
            ),
            'grp.org.hr.workplaces.show.clocking_machines.show.clockings.index' =>
            array_merge(
                (new ShowClockingMachine())->getBreadcrumbs(
                    'grp.org.hr.workplaces.show.clocking_machines.show',
                    [
                        'workplace'       => $routeParameters['workplace'],
                        'clockingMachine' => $routeParameters['clockingMachine']
                    ]
                ),
                $headCrumb([
                    'name'       => 'grp.org.hr.workplaces.show.clocking_machines.show.clockings.index',
                    'parameters' =>
                        [
                            $routeParameters['workplace']->slug,
                            $routeParameters['clockingMachine']->slug,

                        ]
                ])
            ),

            default => []
        };
    }
}
