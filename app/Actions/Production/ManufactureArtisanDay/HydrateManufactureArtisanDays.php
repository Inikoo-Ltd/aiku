<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Oct 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureArtisanDay;

use App\Enums\Production\ManufactureArtisanDay\ManufactureArtisanDayStatusEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Timesheet;
use App\Models\HumanResources\WorkSchedule;
use App\Models\Production\ManufactureArtisanDay;
use App\Models\Production\ManufactureBreak;
use App\Models\Production\ManufactureHoursTolerance;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\Production;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class HydrateManufactureArtisanDays
{
    use AsAction;

    public const int MAX_DAYS = 92;

    /** @var array<int, WorkSchedule|null> */
    private array $schedules = [];

    /**
     * Works out every artisan day of the period again from the floor sessions. A day belongs to the shift it was
     * started in, so a night shift counts on the evening it began.
     */
    public function handle(Production $production, Carbon $from, Carbon $to): void
    {
        $from     = $from->copy()->max($to->copy()->subDays(self::MAX_DAYS));
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        $sessions = ManufactureTaskSession::where('production_id', $production->id)
            ->whereNotNull('employee_id')
            ->whereIn('state', [ManufactureTaskSessionStateEnum::CLOSED, ManufactureTaskSessionStateEnum::OPEN])
            ->whereBetween('started_at', [$from->copy()->startOfDay()->subDay(), $to->copy()->endOfDay()->addDay()])
            ->with('employee.organisation.timezone')
            ->get();

        $breaksByUser = ManufactureBreak::whereIn('user_id', $sessions->pluck('user_id')->unique())
            ->whereBetween('started_at', [$from->copy()->startOfDay()->subDay(), $to->copy()->endOfDay()->addDay()])
            ->get(['user_id', 'started_at'])
            ->groupBy('user_id');

        $days = $sessions
            ->groupBy(fn (ManufactureTaskSession $session) => $session->employee_id.'|'.$this->shiftDay($session->employee, $session->started_at))
            ->filter(function (Collection $daySessions, string $key) use ($fromDate, $toDate) {
                $date = explode('|', $key)[1];

                return $date >= $fromDate && $date <= $toDate;
            });

        $employees = $sessions->pluck('employee')->keyBy('id');
        $employees = $employees->union(
            Employee::whereIn('id', array_diff(self::rosterEmployeeIds($production, $from, $to), $employees->keys()->all()))
                ->with('organisation.timezone')
                ->get()
                ->keyBy('id')
        );
        $clockings = self::clockings($employees->keys()->all(), $fromDate, $toDate);
        foreach ($clockings as $key => $timesheet) {
            if (!$days->has($key) && $timesheet->number_time_trackers > 0) {
                $days->put($key, collect());
            }
        }

        $existing = ManufactureArtisanDay::where('production_id', $production->id)
            ->whereBetween('date', [$fromDate, $toDate])
            ->get()
            ->keyBy(fn (ManufactureArtisanDay $day) => $day->employee_id.'|'.$day->date->toDateString());

        foreach ($existing as $key => $day) {
            if (!$days->has($key)) {
                $day->resolution ? $days->put($key, collect()) : $day->delete();
            }
        }

        $tolerances = [];
        foreach ($days as $key => $daySessions) {
            [$employeeId, $date] = explode('|', $key);
            $employee            = $employees[$employeeId] ?? $existing[$key]->employee;
            $tolerances[$date] ??= ManufactureHoursTolerance::minutesOn($production->id, $date);

            $this->hydrateDay($production, $employee, $date, $daySessions, $tolerances[$date], $existing[$key] ?? null, $breaksByUser, $clockings[$key] ?? null);
        }
    }

    /**
     * The artisans of a period: everyone who closed a session in the production in the eight weeks up to its end, so a
     * day or a week clocked with no work on the tablet is still checked and paid. Staff hidden from the artisan roster
     * (someone from another department who used a tablet once) are left out.
     *
     * @return array<int, int>
     */
    public static function rosterEmployeeIds(Production $production, Carbon $from, Carbon $to): array
    {
        // ponytail: an artisan off the tablet for over eight weeks drops out; an artisan flag on the employee if that bites
        return ManufactureTaskSession::where('production_id', $production->id)
            ->where('state', ManufactureTaskSessionStateEnum::CLOSED)
            ->whereNotNull('employee_id')
            ->whereNotIn('employee_id', Arr::get($production->data, 'hidden_artisan_ids', []))
            ->whereBetween('ended_at', [$from->copy()->startOfDay()->subWeeks(8), $to->copy()->endOfDay()])
            ->distinct()
            ->pluck('employee_id')
            ->all();
    }

    /**
     * The artisans' timesheets: working time is what they clocked, less the breaks they clocked out for.
     *
     * @param  array<int, int>  $employeeIds
     * @return Collection<string, Timesheet> keyed by employee id|date
     */
    public static function clockings(array $employeeIds, string $fromDate, string $toDate): Collection
    {
        return Timesheet::where('subject_type', 'Employee')
            ->whereIn('subject_id', $employeeIds)
            ->whereBetween('date', [$fromDate, $toDate])
            ->get(['subject_id', 'date', 'working_duration', 'breaks_duration', 'number_time_trackers', 'number_open_time_trackers'])
            ->keyBy(fn (Timesheet $timesheet) => $timesheet->subject_id.'|'.$timesheet->date->toDateString());
    }

    private function schedule(Employee $employee): ?WorkSchedule
    {
        if (!array_key_exists($employee->id, $this->schedules)) {
            $this->schedules[$employee->id] = $employee->getEffectiveWorkSchedule()?->load('days', 'timezone');
        }

        return $this->schedules[$employee->id];
    }

    private function shiftDay(Employee $employee, Carbon $startedAt): string
    {
        $schedule = $this->schedule($employee);
        $window   = $schedule ? $employee->shiftWindowOn($startedAt, $schedule) : null;
        if ($window) {
            return $window[0]->toDateString();
        }

        return $startedAt->copy()->setTimezone($employee->organisation->timezone?->name ?? config('app.timezone'))->toDateString();
    }

    /**
     * @param  array{start: Carbon, end: Carbon, unpaid_break_minutes: int}|array{}  $shift
     */
    private function isDayRunning(Employee $employee, string $date, array $shift): bool
    {
        if ($shift) {
            return now()->lessThan($shift['end']);
        }

        return $date >= now()->setTimezone($employee->organisation->timezone?->name ?? config('app.timezone'))->toDateString();
    }

    /**
     * The day is checked on the time clocked; the tablet time is kept beside it, and tablet time beyond the clocked time
     * is flagged on its own once the day is over.
     *
     * @param  Collection<int, Collection<int, ManufactureBreak>>  $breaksByUser
     */
    private function hydrateDay(Production $production, Employee $employee, string $date, Collection $sessions, int $tolerance, ?ManufactureArtisanDay $day, Collection $breaksByUser, ?Timesheet $timesheet): void
    {
        $schedule        = $this->schedule($employee);
        $shift           = $schedule ? $employee->scheduledShiftOn($date, $schedule) : null;
        $expectedMinutes = match (true) {
            $shift === null => null,
            $shift === []   => 0,
            default         => max(0, (int) round($shift['start']->diffInMinutes($shift['end']) - $shift['unpaid_break_minutes'])),
        };

        $isWaitingForTimesCheck = fn (ManufactureTaskSession $session) => $session->job_order_item_task_id !== null
            && $session->left_open_after_shift_at !== null
            && $session->shift_end_resolved_at === null;

        $isDayRunning    = $expectedMinutes !== null && $this->isDayRunning($employee, $date, $shift);
        $stillOpen       = $sessions->filter(fn (ManufactureTaskSession $session) => $session->state == ManufactureTaskSessionStateEnum::OPEN && !$isWaitingForTimesCheck($session));
        $runningJobs     = $sessions->filter($isWaitingForTimesCheck)->count() + ($isDayRunning ? 0 : $stillOpen->count());
        $recordedMinutes = (int) round($sessions
            ->filter(fn (ManufactureTaskSession $session) => $session->state == ManufactureTaskSessionStateEnum::CLOSED && !$isWaitingForTimesCheck($session))
            ->sum(fn (ManufactureTaskSession $session) => (float) ($session->hours ?? $session->paidHours())) * 60);
        $clockedMinutes  = $timesheet ? (int) round($timesheet->working_duration / 60) : null;

        $status = match (true) {
            $expectedMinutes === null                                   => ManufactureArtisanDayStatusEnum::NO_SHIFT,
            $runningJobs > 0                                            => ManufactureArtisanDayStatusEnum::RUNNING_JOB,
            $isDayRunning                                               => ManufactureArtisanDayStatusEnum::IN_PROGRESS,
            ($clockedMinutes ?? 0) > $expectedMinutes + $tolerance           => ManufactureArtisanDayStatusEnum::OVER,
            ($clockedMinutes ?? 0) < $expectedMinutes - $tolerance           => ManufactureArtisanDayStatusEnum::UNDER,
            !$timesheet?->breaks_duration && $this->isMissingBreak($sessions, $shift, $breaksByUser) => ManufactureArtisanDayStatusEnum::NO_BREAK,
            default                                                     => ManufactureArtisanDayStatusEnum::OK,
        };

        $day ??= new ManufactureArtisanDay([
            'group_id'        => $production->group_id,
            'organisation_id' => $production->organisation_id,
            'production_id'   => $production->id,
            'employee_id'     => $employee->id,
            'date'            => $date,
        ]);

        $day->fill([
            'user_id'                => $sessions->first()?->user_id ?? $day->user_id ?? $employee->user_id,
            'shift_start'            => isset($shift['start']) ? $shift['start']->copy()->setTimezone(config('app.timezone')) : null,
            'shift_end'              => isset($shift['end']) ? $shift['end']->copy()->setTimezone(config('app.timezone')) : null,
            'expected_minutes'       => $expectedMinutes,
            'recorded_minutes'       => $recordedMinutes,
            'clocked_minutes'        => $clockedMinutes,
            'tablet_over_clocked_minutes' => $this->isDayRunning($employee, $date, $shift ?: []) ? 0 : max(0, $recordedMinutes - ($clockedMinutes ?? 0)),
            'open_clockings'         => $timesheet->number_open_time_trackers ?? 0,
            'tolerance_minutes'      => $tolerance,
            'unchecked_running_jobs' => $runningJobs,
            'status'                 => $status,
        ]);
        $day->is_warning_open = $day->needsAction();
        $day->save();
    }

    /**
     * A day with an unpaid break in the profile but no break clocked between its first job and its last. The break is
     * never deducted from pay: the day is flagged for a supervisor to check instead.
     *
     * @param  array{start: Carbon, end: Carbon, unpaid_break_minutes: int}|array{}  $shift
     * @param  Collection<int, Collection<int, ManufactureBreak>>  $breaksByUser
     */
    private function isMissingBreak(Collection $sessions, array $shift, Collection $breaksByUser): bool
    {
        if (($shift['unpaid_break_minutes'] ?? 0) <= 0 || $sessions->isEmpty()) {
            return false;
        }

        $firstStart = $sessions->min('started_at');
        $lastEnd    = $sessions->max(fn (ManufactureTaskSession $session) => $session->ended_at ?? now());

        return !$breaksByUser->get($sessions->first()->user_id, collect())
            ->contains(fn (ManufactureBreak $break) => $break->started_at->greaterThanOrEqualTo($firstStart) && $break->started_at->lessThan($lastEnd));
    }
}
