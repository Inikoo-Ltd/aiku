<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Apr 2024 09:57:32 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Timesheet\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\HumanResources\Clocking\UI\IndexClockings;
use App\Actions\HumanResources\Employee\UI\ShowEmployee;
use App\Actions\HumanResources\TimeTracker\UI\IndexTimeTrackers;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Enums\UI\HumanResources\TimesheetTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\HumanResources\ClockingsResource;
use App\Http\Resources\HumanResources\TimesheetsResource;
use App\Http\Resources\HumanResources\TimeTrackersResource;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Timesheet;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowTimesheet extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    private Employee|Organisation $parent;

    public function handle(Timesheet $timesheet): Timesheet
    {
        return $timesheet;
    }

    public function asController(Organisation $organisation, Timesheet $timesheet, ActionRequest $request): Timesheet
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request)->withTab(TimesheetTabsEnum::values());

        return $this->handle($timesheet);
    }

    public function inEmployee(Organisation $organisation, Employee $employee, Timesheet $timesheet, ActionRequest $request): Timesheet
    {
        $this->parent = $employee;
        $this->initialisation($organisation, $request)->withTab(TimesheetTabsEnum::values());

        return $this->handle($timesheet);
    }

    public function htmlResponse(Timesheet $timesheet, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/HumanResources/Timesheet',
            [
                'title'       => __('timesheet'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($timesheet, $request),
                    'next'     => $this->getNext($timesheet, $request),
                ],
                'pageHead'    => [
                    'model' => $timesheet->subject_name,
                    'title' => $timesheet->date->format('l, j F Y'),
                    'edit'  => $this->canEdit ? [
                        'route' => [
                            'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                            'parameters' => $request->route()->originalParameters()
                        ]
                    ] : false,
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => TimesheetTabsEnum::navigation()
                ],

                'timesheet' => GetTimesheetShowcase::run($timesheet),

                TimesheetTabsEnum::TIME_TRACKERS->value => $this->tab == TimesheetTabsEnum::TIME_TRACKERS->value ?
                    fn() => TimeTrackersResource::collection(IndexTimeTrackers::run($timesheet, TimesheetTabsEnum::TIME_TRACKERS->value))
                    : Inertia::lazy(fn() => TimeTrackersResource::collection(IndexTimeTrackers::run($timesheet, TimesheetTabsEnum::TIME_TRACKERS->value))),


                TimesheetTabsEnum::CLOCKINGS->value => $this->tab == TimesheetTabsEnum::CLOCKINGS->value ?
                    fn() => ClockingsResource::collection(IndexClockings::run($timesheet, TimesheetTabsEnum::CLOCKINGS->value))
                    : Inertia::lazy(fn() => ClockingsResource::collection(IndexClockings::run($timesheet, TimesheetTabsEnum::CLOCKINGS->value))),


                TimesheetTabsEnum::HISTORY->value => $this->tab == TimesheetTabsEnum::HISTORY->value ?
                    fn() => HistoryResource::collection(IndexHistory::run($timesheet))
                    : Inertia::lazy(fn() => HistoryResource::collection(IndexHistory::run($timesheet))),

            ]
        )->table(
            IndexClockings::make()->tableStructure(
                parent: $timesheet,
                prefix: TimesheetTabsEnum::CLOCKINGS->value
            )
        )->table(
            IndexTimeTrackers::make()->tableStructure(
                parent: $timesheet,
                prefix: TimesheetTabsEnum::TIME_TRACKERS->value
            )
        );
    }


    public function jsonResponse(Timesheet $timesheet): TimesheetsResource
    {
        return new TimesheetsResource($timesheet);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $timesheet = Timesheet::where('id', $routeParameters['timesheet'])->first();

        return
            match ($routeName) {
                'grp.org.hr.timesheets.show' => array_merge(
                    (new ShowHumanResourcesDashboard())->getBreadcrumbs($routeParameters),
                    [
                        [
                            'type'           => 'modelWithIndex',
                            'modelWithIndex' => [
                                'index' => [
                                    'route' => [
                                        'name'       => 'grp.org.hr.timesheets.index',
                                        'parameters' => Arr::only($routeParameters, 'organisation')
                                    ],
                                    'label' => __('Timesheets')
                                ],
                                'model' => [
                                    'route' => [
                                        'name'       => 'grp.org.hr.timesheets.show',
                                        'parameters' => $routeParameters
                                    ],
                                    'label' => $timesheet->subject_name.' '.$timesheet->date->format('Y-m-d'),
                                ],
                            ],
                            'suffix'         => $suffix,

                        ],
                    ]
                ),
                'grp.org.hr.employees.show.timesheets.show' => array_merge(
                    ShowEmployee::make()->getBreadcrumbs($timesheet->subject, Arr::only($routeParameters, ['organisation', 'employee'])),
                    [
                        [
                            'type'           => 'modelWithIndex',
                            'modelWithIndex' => [
                                'index' => [
                                    'route' => [
                                        'name'       => 'grp.org.hr.employees.show',
                                        'parameters' => Arr::only($routeParameters, ['organisation', 'employee'])
                                    ],
                                    'label' => __('Timesheets')
                                ],
                                'model' => [
                                    'label' => $timesheet->date->format('Y-m-d'),
                                ],
                            ],
                            'suffix'         => $suffix,

                        ],
                    ]
                ),
            };
    }

    public function getPrevious(Timesheet $timesheet, ActionRequest $request): ?array
    {
        $previous = Timesheet::where('date', '<', $timesheet->date);
        if ($this->parent instanceof Organisation) {
            $previous->where('organisation_id', $this->parent->id);
        } else {
            $previous->where('subject_type', 'Employee')->where('subject_id', $this->parent->id);
        }

        $previous = $previous->orderBy('date', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Timesheet $timesheet, ActionRequest $request): ?array
    {
        $next = Timesheet::where('date', '>', $timesheet->date);
        if ($this->parent instanceof Organisation) {
            $next->where('organisation_id', $this->parent->id);
        } else {
            $next->where('subject_type', 'Employee')->where('subject_id', $this->parent->id);
        }
        $next = $next->orderBy('date')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Timesheet $timesheet, string $routeName): ?array
    {
        if (!$timesheet) {
            return null;
        }

        /** @var Employee|Guest $subject */
        $subject = $timesheet->subject;


        return match ($routeName) {
            'grp.org.hr.timesheets.show' => [
                'label' => $timesheet->subject_name.' '.$timesheet->date->format('l, j F Y'),
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'timesheet'    => $timesheet->id,
                    ]
                ]
            ],
            'grp.org.hr.employees.show.timesheets.show' => [
                'label' => $timesheet->date->format('l, j F Y'),
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'employee'     => $subject->slug,
                        'timesheet'    => $timesheet->id,
                    ]
                ]
            ]
        };
    }
}
