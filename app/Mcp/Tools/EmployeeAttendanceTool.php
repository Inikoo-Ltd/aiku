<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\HumanResources\Timesheet\CalculateTimesheetOvertime;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Enums\SysAdmin\Authorisation\OrganisationPermissionsEnum;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Leave;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Attendance and timesheet breakdown for one employee over a date range. Splits clocked time into scheduled hours, approved overtime and unapproved overtime using the same calculation as the timesheets screen, and reports days that are still open or look wrong. Returns HOURS ONLY — it does not and cannot compute pay, because no pay rate is stored in Aiku. Never multiply these hours by a rate you obtained elsewhere without a human confirming which hours are payable.')]
#[IsReadOnly]
class EmployeeAttendanceTool extends AikuOrganisationTool
{
    protected function permission(): OrganisationPermissionsEnum
    {
        return OrganisationPermissionsEnum::HUMAN_RESOURCES_VIEW;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'organisation' => ['required', 'string'],
            'employee'     => ['required', 'string'],
            'from'         => ['required', 'date'],
            'to'           => ['required', 'date', 'after_or_equal:from'],
        ]);

        $organisation = $this->authorisedOrganisation($request);
        if (!$organisation) {
            return $this->organisationNotFoundError($request);
        }

        $employee = Employee::where('slug', $request->string('employee'))
            ->where('organisation_id', $organisation->id)
            ->first();

        if (!$employee) {
            return Response::error('Employee not found.');
        }

        $from = $request->date('from');
        $to   = $request->date('to');

        $timesheets = $employee->timesheets()
            ->with(['timeTrackers', 'organisation'])
            ->whereBetween('date', [$from, $to])
            ->get();

        $overtimeByTimesheetId = CalculateTimesheetOvertime::make()->handleMany($timesheets);

        $scheduledSeconds        = 0;
        $approvedOvertimeSeconds = 0;
        $unapprovedOvertime      = 0;
        $payableEquivalent       = 0;
        $clockedSeconds          = 0;
        $openDays                = [];
        $inconsistentDays        = [];

        foreach ($timesheets as $timesheet) {
            $split = $overtimeByTimesheetId->get($timesheet->id, [
                'paid_duration'                        => 0,
                'paid_overtime_duration'               => 0,
                'unpaid_overtime_duration'             => 0,
                'payable_overtime_equivalent_duration' => 0,
            ]);

            $scheduledSeconds        += $split['paid_duration'];
            $approvedOvertimeSeconds += $split['paid_overtime_duration'];
            $unapprovedOvertime      += $split['unpaid_overtime_duration'];
            $payableEquivalent       += $split['payable_overtime_equivalent_duration'];
            $clockedSeconds          += $timesheet->working_duration;

            if ($timesheet->number_open_time_trackers > 0) {
                $openDays[] = $timesheet->date->toDateString();
            }

            if ($timesheet->working_duration > $timesheet->total_duration) {
                $inconsistentDays[] = $timesheet->date->toDateString();
            }
        }

        $leaves = Leave::where('employee_id', $employee->id)
            ->where('status', LeaveStatusEnum::APPROVED)
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from)
            ->get(['start_date', 'end_date', 'duration_days', 'type']);

        return Response::json([
            'employee'            => $employee->contact_name,
            'from'                => $request->string('from'),
            'to'                  => $request->string('to'),
            'days_with_timesheet' => $timesheets->count(),

            'hours' => [
                'scheduled'            => $this->hours($scheduledSeconds),
                'approved_overtime'    => $this->hours($approvedOvertimeSeconds),
                'unapproved_overtime'  => $this->hours($unapprovedOvertime),
                'total_clocked'        => $this->hours($clockedSeconds),
            ],

            'payable_hours_after_overtime_multiplier' => $this->hours($scheduledSeconds + $payableEquivalent),

            'approved_leave' => $leaves->map(fn (Leave $leave) => [
                'from'  => $leave->start_date->toDateString(),
                'to'    => $leave->end_date->toDateString(),
                'days'  => $leave->duration_days,
                'type'  => $leave->type,
            ])->values(),

            'warnings' => $this->warnings(
                $openDays,
                $inconsistentDays,
                $leaves->count(),
                $unapprovedOvertime,
                $clockedSeconds - ($scheduledSeconds + $approvedOvertimeSeconds + $unapprovedOvertime)
            ),

            'how_to_read_this' => 'scheduled = clocked time inside the work schedule. approved_overtime = time outside it covered by an approved overtime request that is compensated in money. unapproved_overtime = worked but never approved, or compensated as time in lieu rather than money, so it is NOT automatically payable. total_clocked is the raw sum and equals the other three; do not add it to them. payable_hours_after_overtime_multiplier is the only figure to multiply by a rate: it is scheduled hours plus approved overtime already weighted by its overtime type multiplier, so at 1.5x an overtime hour counts as 1.5. Leave is listed separately because leave days never produce a timesheet. Aiku stores no pay rate, so no wage figure can be derived here.',
        ]);
    }

    private function hours(int $seconds): float
    {
        return round($seconds / 3600, 2);
    }

    /**
     * @param  array<int, string>  $openDays
     * @param  array<int, string>  $inconsistentDays
     * @return array<int, string>
     */
    private function warnings(array $openDays, array $inconsistentDays, int $leaveCount, int $unapprovedOvertime, int $unreconciledSeconds): array
    {
        $warnings = [];

        if (abs($unreconciledSeconds) >= 60) {
            $warnings[] = 'DOES NOT RECONCILE: '.$this->hours(abs($unreconciledSeconds)).' clocked hour(s) could not be attributed to scheduled time or overtime, usually because the day has no clock-in/clock-out records behind it. Do not pay from these figures until this is explained.';
        }

        if ($openDays) {
            $warnings[] = 'UNDERSTATED: '.count($openDays).' day(s) still have an open clock-in, and open time is excluded until the employee clocks out: '.implode(', ', array_slice($openDays, 0, 10)).'. Close these before paying.';
        }

        if ($inconsistentDays) {
            $warnings[] = 'SUSPECT DATA: '.count($inconsistentDays).' day(s) have working time greater than the clock-in to clock-out span, which should be impossible: '.implode(', ', array_slice($inconsistentDays, 0, 10)).'. Check these manually.';
        }

        if ($unapprovedOvertime > 0) {
            $warnings[] = 'Unapproved overtime is present and is excluded from scheduled and approved figures. Decide deliberately whether to pay it.';
        }

        if ($leaveCount > 0) {
            $warnings[] = 'This employee has approved leave in this range. Leave days produce no timesheet, so they are absent from every hours figure above.';
        }

        return $warnings;
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organisation' => $schema->string()->description('Organisation slug or code')->required(),
            'employee'     => $schema->string()->description('Employee slug')->required(),
            'from'         => $schema->string()->description('Start date (Y-m-d)')->required(),
            'to'           => $schema->string()->description('End date (Y-m-d), inclusive')->required(),
        ];
    }
}
