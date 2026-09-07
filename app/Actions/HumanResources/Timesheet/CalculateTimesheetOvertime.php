<?php

namespace App\Actions\HumanResources\Timesheet;

use App\Enums\HumanResources\Overtime\OvertimeCompensationTypeEnum;
use App\Enums\HumanResources\Overtime\OvertimeRequestStatusEnum;
use App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\OvertimeRequest;
use App\Models\HumanResources\Timesheet;
use App\Models\HumanResources\WorkSchedule;
use App\Models\HumanResources\WorkScheduleDay;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateTimesheetOvertime
{
    use AsAction;

    public function handle(Timesheet $timesheet): array
    {
        $timesheet->loadMissing(['timeTrackers', 'organisation', 'subject']);

        $employee = $timesheet->subject_type === 'Employee' ? $timesheet->subject : null;
        $schedule = $employee?->getDefaultWorkSchedule() ?? $timesheet->organisation?->getDefaultWorkSchedule();

        $scheduleDaysByIso = $this->scheduleDaysFor($schedule);
        $approvedAllowances = $this->approvedAllowancesFor($timesheet);

        return $this->calculateForTimesheet($timesheet, $scheduleDaysByIso, $approvedAllowances);
    }

    /**
     * @param  Collection<int, Timesheet>  $timesheets  Must have timeTrackers eager loaded; organisation is loaded here if missing.
     * @return Collection<int, array{paid_duration: int, unpaid_overtime_duration: int, paid_overtime_duration: int, payable_overtime_equivalent_duration: int}> keyed by timesheet id
     */
    public function handleMany(Collection $timesheets): Collection
    {
        if ($timesheets->isEmpty()) {
            return collect();
        }

        $timesheets->loadMissing('organisation');

        $scheduleDaysByOrganisation = $timesheets->pluck('organisation')
            ->filter()
            ->unique('id')
            ->mapWithKeys(fn (Organisation $organisation) => [$organisation->id => $this->scheduleDaysFor($organisation->getDefaultWorkSchedule())]);

        $employeeIds = $timesheets->where('subject_type', 'Employee')->pluck('subject_id')->unique()->all();

        $scheduleDaysByEmployee = Employee::whereIn('id', $employeeIds)
            ->get()
            ->mapWithKeys(fn (Employee $employee) => [$employee->id => $this->scheduleDaysFor($employee->getDefaultWorkSchedule())]);

        $dates = $timesheets->pluck('date')->map(fn ($date) => $date->toDateString())->unique()->all();

        $approvedOvertimeByEmployeeAndDate = OvertimeRequest::with('overtimeType')
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('requested_date', $dates)
            ->where('status', OvertimeRequestStatusEnum::APPROVED)
            ->get()
            ->groupBy(fn (OvertimeRequest $overtimeRequest) => $overtimeRequest->employee_id.'|'.$overtimeRequest->requested_date->toDateString())
            ->map(fn (Collection $group) => $this->allowances($group));

        return $timesheets->mapWithKeys(function (Timesheet $timesheet) use ($scheduleDaysByOrganisation, $scheduleDaysByEmployee, $approvedOvertimeByEmployeeAndDate) {
            $scheduleDaysByIso = null;
            if ($timesheet->subject_type === 'Employee') {
                $scheduleDaysByIso = $scheduleDaysByEmployee->get($timesheet->subject_id);
            }

            if (!$scheduleDaysByIso || $scheduleDaysByIso->isEmpty()) {
                $scheduleDaysByIso = $scheduleDaysByOrganisation->get($timesheet->organisation_id) ?? collect();
            }

            $key = $timesheet->subject_id.'|'.$timesheet->date->toDateString();
            $approvedAllowances = $approvedOvertimeByEmployeeAndDate->get($key, []);

            return [$timesheet->id => $this->calculateForTimesheet($timesheet, $scheduleDaysByIso, $approvedAllowances)];
        });
    }

    /**
     * @param  array<int, array{seconds: int, multiplier: float}>  $approvedAllowances
     */
    private function calculateForTimesheet(Timesheet $timesheet, Collection $scheduleDaysByIso, array $approvedAllowances): array
    {
        $scheduledWindow = $this->resolveScheduledWindow($timesheet, $scheduleDaysByIso);

        $paidDuration           = 0;
        $unpaidOvertimeDuration = 0;

        foreach ($timesheet->timeTrackers as $timeTracker) {
            if ($timeTracker->status != TimeTrackerStatusEnum::CLOSED) {
                continue;
            }

            $overlapSeconds         = $this->overlapSeconds($timeTracker->starts_at, $timeTracker->ends_at, $scheduledWindow);
            $paidDuration           += $overlapSeconds;
            $unpaidOvertimeDuration += max(0, (int) $timeTracker->duration - $overlapSeconds);
        }

        $paidOvertimeDuration = 0;
        $payableEquivalent    = 0.0;
        $remaining            = $unpaidOvertimeDuration;

        foreach ($approvedAllowances as $allowance) {
            if ($remaining <= 0) {
                break;
            }

            $covered              = min($remaining, $allowance['seconds']);
            $paidOvertimeDuration += $covered;
            $payableEquivalent    += $covered * $allowance['multiplier'];
            $remaining            -= $covered;
        }

        $unpaidOvertimeDuration -= $paidOvertimeDuration;

        return [
            'paid_duration'            => $paidDuration,
            'unpaid_overtime_duration' => $unpaidOvertimeDuration,
            'paid_overtime_duration'   => $paidOvertimeDuration,
            'payable_overtime_equivalent_duration' => (int) round($payableEquivalent),
        ];
    }

    /**
     * Only overtime compensated in money earns a multiplier. Time in lieu is banked as leave
     * and unpaid overtime buys nothing, so neither is treated as approved here — their time
     * stays in unpaid_overtime_duration, which keeps paid + paid overtime + unpaid overtime
     * equal to the hours actually clocked.
     *
     * @param  Collection<int, OvertimeRequest>  $overtimeRequests
     * @return array<int, array{seconds: int, multiplier: float}>
     */
    private function allowances(Collection $overtimeRequests): array
    {
        return $overtimeRequests
            ->filter(fn (OvertimeRequest $overtimeRequest) => $overtimeRequest->overtimeType?->compensation_type === OvertimeCompensationTypeEnum::PAID)
            ->map(fn (OvertimeRequest $overtimeRequest) => [
                'seconds'    => (int) ($overtimeRequest->recorded_duration_minutes ?? $overtimeRequest->requested_duration_minutes ?? 0) * 60,
                'multiplier' => (float) ($overtimeRequest->overtimeType->multiplier ?? 1),
            ])
            ->filter(fn (array $allowance) => $allowance['seconds'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, WorkScheduleDay> keyed by ISO day of week
     */
    private function scheduleDaysFor(?WorkSchedule $schedule): Collection
    {
        if (!$schedule) {
            return collect();
        }

        return $schedule->days->keyBy('day_of_week')->map(function (WorkScheduleDay $day) use ($schedule) {
            $day->setRelation('workSchedule', $schedule);

            return $day;
        });
    }

    private function resolveScheduledWindow(Timesheet $timesheet, Collection $scheduleDaysByIso): ?array
    {
        if ($timesheet->subject_type !== 'Employee') {
            return null;
        }

        /** @var WorkScheduleDay|null $scheduleDay */
        $scheduleDay = $scheduleDaysByIso->get($timesheet->date->dayOfWeekIso);

        if (!$scheduleDay || !$scheduleDay->is_working_day || !$scheduleDay->start_time || !$scheduleDay->end_time) {
            return null;
        }

        $organisation = $timesheet->organisation;
        $timezone     = $scheduleDay->workSchedule?->timezone?->name ?? $organisation?->timezone?->name ?? config('app.timezone');

        $start = Carbon::parse($timesheet->date->toDateString().' '.$scheduleDay->start_time, $timezone);
        $end   = Carbon::parse($timesheet->date->toDateString().' '.$scheduleDay->end_time, $timezone);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    private function overlapSeconds(?Carbon $start, ?Carbon $end, ?array $scheduledWindow): int
    {
        if (!$start || !$end || !$scheduledWindow) {
            return 0;
        }

        [$scheduledStart, $scheduledEnd] = $scheduledWindow;

        $overlapStart = $start->greaterThan($scheduledStart) ? $start : $scheduledStart;
        $overlapEnd   = $end->lessThan($scheduledEnd) ? $end : $scheduledEnd;

        if ($overlapEnd->lessThanOrEqualTo($overlapStart)) {
            return 0;
        }

        return (int) $overlapStart->diffInSeconds($overlapEnd);
    }

    /**
     * @return array<int, array{seconds: int, multiplier: float}>
     */
    private function approvedAllowancesFor(Timesheet $timesheet): array
    {
        if ($timesheet->subject_type !== 'Employee') {
            return [];
        }

        return $this->allowances(
            OvertimeRequest::with('overtimeType')
                ->where('employee_id', $timesheet->subject_id)
                ->where('requested_date', $timesheet->date->toDateString())
                ->where('status', OvertimeRequestStatusEnum::APPROVED)
                ->get()
        );
    }
}
