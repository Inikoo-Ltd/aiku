<?php

/*
 * Author Louis Perez
 * Created on 11-11-2026-09h-04m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Clocking;

use App\Actions\HumanResources\Clocking\UpdateClockingNotes;
use App\Actions\HumanResources\Employee\UI\IndexClockingEmployees;
use App\Actions\SysAdmin\User\GetUserCurrentEmployee;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Timesheet;
use App\Models\HumanResources\TimeTracker;
use App\Models\HumanResources\WorkSchedule;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetMobileClocking
{
    use AsController;

    public function handle(User $user): array
    {
        $employee = GetUserCurrentEmployee::run($user);

        if (!$employee) {
            return [
                'employee'   => null,
                'status'     => 'clocked_out',
                'since'      => null,
                'today'      => null,
                'sessions'   => [],
                'methods'    => [],
                'code'       => null,
                'has_shifts' => false,
                'can_edit_notes' => false,
            ];
        }

        $timezone  = $employee->organisation->timezone?->name ?? config('app.timezone');
        $timesheet = $this->todayTimesheet($employee, $timezone);
        $isClockedIn = $timesheet && $timesheet->number_open_time_trackers > 0;
        $openTracker = $isClockedIn
            ? TimeTracker::where('timesheet_id', $timesheet->id)->where('status', 'open')->whereNull('ends_at')->first()
            : null;
        $clockingPage = IndexClockingEmployees::make();

        return [
            'employee'   => [
                'id'           => $employee->id,
                'name'         => $employee->contact_name,
                'organisation' => [
                    'id'   => $employee->organisation->id,
                    'code' => $employee->organisation->code,
                    'name' => $employee->organisation->name,
                ],
            ],
            'timezone'   => $timezone,
            'status'     => $isClockedIn ? 'clocked_in' : 'clocked_out',
            'since'      => $openTracker?->starts_at?->timezone($timezone)->toIso8601String(),
            'today'      => $timesheet ? $this->timesheetSummary($timesheet, $timezone) : null,
            'sessions'   => $timesheet ? $clockingPage->getClockingSessions($timesheet, $timezone) : [],
            'methods'    => $clockingPage->getAvailableClockingMethods($employee->organisation_id),
            'code'       => $this->displayCode($employee),
            'has_shifts' => WorkSchedule::where('schedulable_type', 'Organisation')
                ->where('schedulable_id', $employee->organisation_id)
                ->where('type', 'shift')
                ->where('is_active', true)
                ->exists(),
            'can_edit_notes' => UpdateClockingNotes::canEditClockings($user, $employee->organisation),
        ];
    }

    private function todayTimesheet(Employee $employee, string $timezone): ?Timesheet
    {
        return Timesheet::where('subject_type', 'Employee')
            ->where('subject_id', $employee->id)
            ->where('date', now()->timezone($timezone)->toDateString())
            ->first();
    }

    /**
     * @return array{date: string, first_clock_in: string|null, last_clock_out: string|null, working_seconds: int, breaks_seconds: int}
     */
    public function timesheetSummary(Timesheet $timesheet, string $timezone): array
    {
        return [
            'date'            => $timesheet->date->toDateString(),
            'first_clock_in'  => $timesheet->start_at?->timezone($timezone)->toIso8601String(),
            'last_clock_out'  => $timesheet->end_at?->timezone($timezone)->toIso8601String(),
            'working_seconds' => (int) $timesheet->working_duration,
            'breaks_seconds'  => (int) $timesheet->breaks_duration,
        ];
    }

    private function displayCode(Employee $employee): ?string
    {
        $code = preg_replace('/^\d+:/', '', (string) $employee->pin);

        return $code === '' ? null : $code;
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $this->handle($user)]);
    }
}
