<?php

namespace App\Actions\HumanResources\Timesheet;

use App\Enums\HumanResources\Overtime\OvertimeRequestStatusEnum;
use App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum;
use App\Models\HumanResources\OvertimeRequest;
use App\Models\HumanResources\Timesheet;
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
        $timesheet->loadMissing(['timeTrackers', 'organisation']);

        $scheduleDaysByIso = $this->scheduleDaysByIso($timesheet->organisation);
        $approvedOvertimeSeconds = $this->approvedOvertimeSecondsFor($timesheet);

        return $this->calculateForTimesheet($timesheet, $scheduleDaysByIso, $approvedOvertimeSeconds);
    }

    /**
     * @param  Collection<int, Timesheet>  $timesheets  Must have timeTrackers eager loaded; organisation is loaded here if missing.
     * @return Collection<int, array{paid_duration: int, unpaid_overtime_duration: int, paid_overtime_duration: int}> keyed by timesheet id
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
            ->mapWithKeys(fn (Organisation $organisation) => [$organisation->id => $this->scheduleDaysByIso($organisation)]);

        $employeeIds = $timesheets->where('subject_type', 'Employee')->pluck('subject_id')->unique()->all();
        $dates       = $timesheets->pluck('date')->map(fn ($date) => $date->toDateString())->unique()->all();

        $approvedOvertimeByEmployeeAndDate = OvertimeRequest::whereIn('employee_id', $employeeIds)
            ->whereIn('requested_date', $dates)
            ->where('status', OvertimeRequestStatusEnum::APPROVED)
            ->get()
            ->groupBy(fn (OvertimeRequest $overtimeRequest) => $overtimeRequest->employee_id.'|'.$overtimeRequest->requested_date->toDateString())
            ->map(fn (Collection $group) => (int) $group->sum(fn (OvertimeRequest $overtimeRequest) => ($overtimeRequest->recorded_duration_minutes ?? $overtimeRequest->requested_duration_minutes ?? 0) * 60));

        return $timesheets->mapWithKeys(function (Timesheet $timesheet) use ($scheduleDaysByOrganisation, $approvedOvertimeByEmployeeAndDate) {
            $scheduleDaysByIso = $scheduleDaysByOrganisation->get($timesheet->organisation_id) ?? collect();
            $key = $timesheet->subject_id.'|'.$timesheet->date->toDateString();
            $approvedOvertimeSeconds = $approvedOvertimeByEmployeeAndDate->get($key, 0);

            return [$timesheet->id => $this->calculateForTimesheet($timesheet, $scheduleDaysByIso, $approvedOvertimeSeconds)];
        });
    }

    private function calculateForTimesheet(Timesheet $timesheet, Collection $scheduleDaysByIso, int $approvedOvertimeSeconds): array
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

        $paidOvertimeDuration = min($unpaidOvertimeDuration, $approvedOvertimeSeconds);
        $unpaidOvertimeDuration -= $paidOvertimeDuration;

        return [
            'paid_duration'            => $paidDuration,
            'unpaid_overtime_duration' => $unpaidOvertimeDuration,
            'paid_overtime_duration'   => $paidOvertimeDuration,
        ];
    }

    /**
     * @return Collection<int, WorkScheduleDay> keyed by ISO day of week
     */
    private function scheduleDaysByIso(?Organisation $organisation): Collection
    {
        $schedule = $organisation?->getDefaultWorkSchedule();

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

    private function approvedOvertimeSecondsFor(Timesheet $timesheet): int
    {
        if ($timesheet->subject_type !== 'Employee') {
            return 0;
        }

        $minutes = OvertimeRequest::where('employee_id', $timesheet->subject_id)
            ->where('requested_date', $timesheet->date->toDateString())
            ->where('status', OvertimeRequestStatusEnum::APPROVED)
            ->get()
            ->sum(fn (OvertimeRequest $overtimeRequest) => $overtimeRequest->recorded_duration_minutes ?? $overtimeRequest->requested_duration_minutes ?? 0);

        return (int) $minutes * 60;
    }
}
