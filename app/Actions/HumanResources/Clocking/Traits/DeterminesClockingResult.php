<?php

namespace App\Actions\HumanResources\Clocking\Traits;

use App\Enums\HumanResources\Clocking\ClockingActionEnum;
use App\Enums\HumanResources\Employee\EmploymentTypeEnum;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\TimeTracker;
use App\Models\HumanResources\WorkSchedule;
use Exception;
use Illuminate\Support\Carbon;

trait DeterminesClockingResult
{
    private function guardAgainstFrequentClocking(Employee $employee): void
    {
        $lastClocking = Clocking::where('subject_type', $employee->getMorphClass())
            ->where('subject_id', $employee->id)
            ->latest('clocked_at')
            ->first();

        if ($lastClocking && $lastClocking->clocked_at->diffInSeconds(now()) < 5) {
            throw new Exception(__('Scan too frequent. Please wait a moment.'));
        }
    }

    private function calculateLateClocking(Employee $employee, Carbon $clockedInAt, ?WorkSchedule $selectedSchedule = null): bool
    {
        if ($employee->employment_type === EmploymentTypeEnum::PART_TIME) {
            return false;
        }

        $gracePeriod = $employee->organisation->late_grace_period_minutes ?? 15;
        $schedule = $selectedSchedule ?? $employee->organisation->getDefaultWorkSchedule();

        if (!$schedule) {
            return false;
        }

        $timezone = $schedule->timezone?->name ?? $employee->organisation->timezone?->name ?? config('app.timezone');
        $todayIso = $clockedInAt->dayOfWeekIso;
        $todaySchedule = $schedule->days()->where('day_of_week', $todayIso)->first();

        if (!$todaySchedule || !$todaySchedule->is_working_day) {
            return false;
        }

        $scheduledStart = Carbon::today($timezone)->setTimeFromTimeString($todaySchedule->start_time);
        $allowedTime = $scheduledStart->copy()->addMinutes($gracePeriod);

        return $clockedInAt->gt($allowedTime);
    }

    private function resolveClockingActionType(Clocking $clocking): ?ClockingActionEnum
    {
        $timeTracker = null;
        if ($clocking->time_tracker_id) {
            $timeTracker = TimeTracker::find($clocking->time_tracker_id);
        }

        if (!$timeTracker) {
            return null;
        }

        if ($timeTracker->start_clocking_id == $clocking->id) {
            return ClockingActionEnum::CLOCK_IN;
        }

        if ($timeTracker->end_clocking_id == $clocking->id) {
            return ClockingActionEnum::CLOCK_OUT;
        }

        return null;
    }
}
