<?php

namespace App\Actions\HumanResources\Employee\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\HumanResources\Clocking\UI\IndexClockings;
use App\Actions\HumanResources\TimeTracker\UI\IndexTimeTrackers;
use App\Actions\HumanResources\Timesheet\UI\GetTimesheetShowcase;
use App\Actions\OrgAction;
use App\Enums\UI\HumanResources\TimesheetTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\HumanResources\ClockingsResource;
use App\Http\Resources\HumanResources\TimeTrackersResource;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Timesheet;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowClockingEmployee extends OrgAction
{
    use AsAction;

    private Employee $parent;

    public function handle(Timesheet $timesheet, ActionRequest $request): Timesheet
    {
        $employee = Auth::user()->employees->first();

        if ($timesheet->subject_id !== $employee->id) {
            abort(403);
        }

        $this->parent = $employee;

        $this->tab = $request->input('tab');
        if (!$this->tab) {
            $this->tab = TimesheetTabsEnum::TIME_TRACKERS->value;
        }

        return $timesheet;
    }

    public function asController(Timesheet $timesheet, ActionRequest $request): Timesheet
    {
        return $this->handle($timesheet, $request);
    }
    public function htmlResponse(Timesheet $timesheet, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/HumanResources/Timesheet',
            [
                'title'       => __('Timesheet Detail'),
                'breadcrumbs' => $this->getBreadcrumbs($timesheet),
                'navigation'  => [
                    'previous' => $this->getPrevious($timesheet),
                    'next'     => $this->getNext($timesheet),
                ],
                'pageHead'    => [
                    'model' => __('Timesheet'),
                    'title' => $timesheet->date->format('l, j F Y'),
                    'edit'  => false,
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => TimesheetTabsEnum::navigation()
                ],

                'timesheet' => GetTimesheetShowcase::run($timesheet),

                TimesheetTabsEnum::TIME_TRACKERS->value => $this->tab == TimesheetTabsEnum::TIME_TRACKERS->value ?
                    fn () => TimeTrackersResource::collection(IndexTimeTrackers::run($timesheet, TimesheetTabsEnum::TIME_TRACKERS->value))
                    : Inertia::lazy(fn () => TimeTrackersResource::collection(IndexTimeTrackers::run($timesheet, TimesheetTabsEnum::TIME_TRACKERS->value))),


                TimesheetTabsEnum::CLOCKINGS->value => $this->tab == TimesheetTabsEnum::CLOCKINGS->value ?
                    fn () => ClockingsResource::collection(IndexClockings::run($timesheet, TimesheetTabsEnum::CLOCKINGS->value))
                    : Inertia::lazy(fn () => ClockingsResource::collection(IndexClockings::run($timesheet, TimesheetTabsEnum::CLOCKINGS->value))),


                TimesheetTabsEnum::HISTORY->value => $this->tab == TimesheetTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($timesheet))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($timesheet))),

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

    public function getPrevious(Timesheet $timesheet): ?array
    {
        $previous = Timesheet::where('date', '<', $timesheet->date)
            ->where('subject_type', 'Employee')
            ->where('subject_id', $this->parent->id)
            ->orderBy('date', 'desc')
            ->first();

        return $this->getNavigation($previous);
    }

    public function getNext(Timesheet $timesheet): ?array
    {
        $next = Timesheet::where('date', '>', $timesheet->date)
            ->where('subject_type', 'Employee')
            ->where('subject_id', $this->parent->id)
            ->orderBy('date')
            ->first();

        return $this->getNavigation($next);
    }

    private function getNavigation(?Timesheet $timesheet): ?array
    {
        if (!$timesheet) {
            return null;
        }

        return [
            'label' => $timesheet->date->format('l, j F Y'),
            'route' => [
                'name'       => 'grp.clocking_employees.show',
                'parameters' => [
                    'timesheet' => $timesheet->id,
                ]
            ]
        ];
    }

    public function getBreadcrumbs(Timesheet $timesheet): array
    {
        return [
            [
                'type'   => 'simple',
                'simple' => [
                    'label' => __('Employee Clocking'),
                    'route' => [
                        'name' => 'grp.clocking_employees.index',
                        'parameters' => ['tab' => 'timesheets']
                    ]
                ]
            ],
            [
                'type'   => 'simple',
                'simple' => [
                    'label' => $timesheet->date->format('Y-m-d'),
                    'route' => [
                        'name' => 'grp.clocking_employees.show',
                        'parameters' => ['timesheet' => $timesheet->id]
                    ]
                ]
            ]
        ];
    }
}
