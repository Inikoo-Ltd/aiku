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
use App\Models\Production\ManufactureOvertime;
use App\Models\Production\ManufacturePaidTimeRule;
use App\Models\Production\ManufacturePayrollAdjustment;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\Production;
use App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

        $sessions = self::artisanSessions($production)
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

        $overtimes = ManufactureOvertime::where('production_id', $production->id)
            ->whereBetween('date', [$fromDate, $toDate])
            ->get()
            ->keyBy(fn (ManufactureOvertime $overtime) => $overtime->employee_id.'|'.$overtime->date->toDateString());

        $employees = $sessions->pluck('employee')->keyBy('id');
        $employees = $employees->union(
            Employee::whereIn('id', array_diff([...self::rosterEmployeeIds($production, $from, $to), ...$overtimes->pluck('employee_id')->all()], $employees->keys()->all()))
                ->with('organisation.timezone')
                ->get()
                ->keyBy('id')
        );
        $clockings  = self::clockings($employees->keys()->all(), $fromDate, $toDate);
        $clockTimes = self::clockTimes($clockings);
        foreach ($clockings as $key => $timesheet) {
            if (!$days->has($key) && $timesheet->number_time_trackers > 0) {
                $days->put($key, collect());
            }
        }
        foreach ($overtimes->keys() as $key) {
            if (!$days->has($key)) {
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

        $paidTime     = $this->paidTime($production, $clockings, $overtimes, $existing);
        $rules        = ManufacturePaidTimeRule::of($production->id);
        $lockedBefore = ManufacturePayrollAdjustment::lockedBefore($production)->toDateString();
        $tolerances   = [];
        foreach ($days as $key => $daySessions) {
            [$employeeId, $date] = explode('|', $key);
            $employee            = $employees[$employeeId] ?? $existing[$key]->employee;
            $tolerances[$date] ??= ManufactureHoursTolerance::minutesOn($production->id, $date);

            $this->hydrateDay($production, $employee, $date, $daySessions, $tolerances[$date], $existing[$key] ?? null, $breaksByUser, $clockings[$key] ?? null, $clockTimes[$key] ?? null, $overtimes[$key] ?? null, $paidTime[$key] ?? null, $date < $lockedBefore && ManufacturePaidTimeRule::limitingOn($rules, $date) !== null);
        }
    }

    /**
     * The time paid on each clocked day a paid time rule limits to the shift (HELP-4629): the time clocked between the
     * shift start and end, a late first clock in counted from the nearest rounding step after the shift start, plus the
     * time clocked outside the shift up to the overtime approved for the day. A day off pays approved overtime only. Days
     * with no rule in force, no working hours set or no clockings to read are left out: their clocked time is paid.
     *
     * The shift of a day in a week locked for payroll is the one kept on its day row (no working hours then stays no
     * working hours), so a later change of somebody's working hours never re-prices a paid week; clockings are still read
     * live, so a correction there shows as a change. Night shifts are not limited.
     *
     * @param  Collection<string, Timesheet>  $clockings  keyed by employee id|date
     * @param  Collection<string, ManufactureOvertime>|null  $overtimes  every decision of the period, keyed by employee id|date
     * @param  Collection<string, ManufactureArtisanDay>|null  $days  the day rows of the period, keyed by employee id|date
     * @return array<string, array{paid: int, outside: int, overtime: int, shift: array}> seconds and the shift they were limited to, keyed by employee id|date
     */
    public function paidTime(Production $production, Collection $clockings, ?Collection $overtimes = null, ?Collection $days = null): array
    {
        $rules   = ManufacturePaidTimeRule::of($production->id);
        $limited = $clockings->filter(fn (Timesheet $timesheet) => ManufacturePaidTimeRule::limitingOn($rules, $timesheet->date->toDateString()));
        if ($limited->isEmpty()) {
            return [];
        }

        $dates       = $limited->map(fn (Timesheet $timesheet) => $timesheet->date->toDateString());
        $employeeIds = $limited->pluck('subject_id')->unique()->values()->all();
        $keyOf       = fn (Model $model) => $model->employee_id.'|'.$model->date->toDateString();
        $overtimes ??= ManufactureOvertime::where('production_id', $production->id)->whereIn('employee_id', $employeeIds)->whereBetween('date', [$dates->min(), $dates->max()])->get()->keyBy($keyOf);
        $days      ??= ManufactureArtisanDay::where('production_id', $production->id)->whereIn('employee_id', $employeeIds)->whereBetween('date', [$dates->min(), $dates->max()])->get()->keyBy($keyOf);

        $employees    = Employee::withTrashed()->whereIn('id', $employeeIds)->with('organisation.timezone')->get()->keyBy('id');
        $lockedBefore = ManufacturePayrollAdjustment::lockedBefore($production)->toDateString();
        $trackers     = DB::table('time_trackers')
            ->whereIn('timesheet_id', $limited->pluck('id'))
            ->whereNull('deleted_at')
            ->where('status', TimeTrackerStatusEnum::CLOSED->value)
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->orderBy('starts_at')
            ->get(['timesheet_id', 'starts_at', 'ends_at'])
            ->groupBy('timesheet_id');

        $paidTime = [];
        foreach ($limited as $key => $timesheet) {
            $date     = $timesheet->date->toDateString();
            $employee = $employees->get($timesheet->subject_id);
            $day      = $days->get($key);
            $shift    = match (true) {
                $day && $date < $lockedBefore => self::keptShift($day),
                $employee !== null            => $this->shiftToLimitTo($employee, $date),
                default                       => null,
            };
            $pairs = $trackers->get($timesheet->id, collect())->map(fn (object $tracker) => [Carbon::parse($tracker->starts_at), Carbon::parse($tracker->ends_at)])->all();
            if ($shift === null || !$pairs || ($employee && self::crossesMidnight($shift, $employee))) {
                continue;
            }

            $overtime = $overtimes->get($key);
            $rule     = ManufacturePaidTimeRule::limitingOn($rules, $date);

            $paidTime[$key] = self::paidSeconds($pairs, (int) $timesheet->working_duration, $shift, $rule->rounding_minutes, $overtime?->isApproved() ? (int) $overtime->approved_minutes : 0)
                + ['shift' => $shift];
        }

        return $paidTime;
    }

    /**
     * @param  array<int, array{0: Carbon, 1: Carbon}>  $pairs  closed clock in and out pairs, earliest first
     * @param  array{start: Carbon, end: Carbon, unpaid_break_minutes: int}|array{}  $shift  empty on a day off
     * @return array{paid: int, outside: int, overtime: int} seconds: the time paid, the time clocked outside the shift, and the part of it the approved overtime covers
     */
    public static function paidSeconds(array $pairs, int $clockedSeconds, array $shift, int $roundingMinutes, int $approvedOvertimeMinutes): array
    {
        if (!$shift) {
            $overtime = min($clockedSeconds, $approvedOvertimeMinutes * 60);

            return ['paid' => $overtime, 'outside' => $clockedSeconds, 'overtime' => $overtime];
        }

        $overlap = fn (Carbon $from, Carbon $to) => max(0, (int) $from->copy()->max($shift['start'])->diffInSeconds($to->copy()->min($shift['end']), false));

        $insideAsClocked = array_sum(array_map(fn (array $pair) => $overlap($pair[0], $pair[1]), $pairs));
        $outside         = max(0, $clockedSeconds - $insideAsClocked);

        $firstIn = $pairs[0][0];
        if ($firstIn->greaterThan($shift['start'])) {
            $step        = max(1, $roundingMinutes) * 60;
            $pairs[0][0] = $shift['start']->copy()->addSeconds((int) (floor($shift['start']->diffInSeconds($firstIn) / $step + 0.5) * $step));
        }
        $inside = array_sum(array_map(fn (array $pair) => $overlap($pair[0], $pair[1]), $pairs));

        $overtime = min($outside, $approvedOvertimeMinutes * 60);

        return ['paid' => $inside + $overtime, 'outside' => $outside, 'overtime' => $overtime];
    }

    /**
     * The scheduled shift of a day not yet locked: empty for a day off, null when no working hours say when the shift is,
     * which includes a working day left without its start or end time (it is not a day off, so its clocked time is paid).
     *
     * @return array{start: Carbon, end: Carbon, unpaid_break_minutes: int}|array{}|null
     */
    private function shiftToLimitTo(Employee $employee, string $date): ?array
    {
        $schedule = $this->schedule($employee);
        $shift    = $schedule ? $employee->scheduledShiftOn($date, $schedule) : null;
        if ($shift === []) {
            $timezone = $schedule->timezone?->name ?? $employee->organisation?->timezone?->name ?? config('app.timezone');

            return $schedule->days->firstWhere('day_of_week', Carbon::parse($date, $timezone)->dayOfWeekIso)?->is_working_day ? null : [];
        }

        return $shift;
    }

    /**
     * A night shift: its clockings after midnight sit on the next day's timesheet, so it cannot be limited day by day.
     *
     * @param  array{start: Carbon, end: Carbon, unpaid_break_minutes: int}|array{}  $shift
     */
    private static function crossesMidnight(array $shift, Employee $employee): bool
    {
        // ponytail: night shifts are paid as clocked; pair the timesheets of both days if a factory with nights turns the rule on
        $timezone = $employee->organisation?->timezone?->name ?? config('app.timezone');

        return $shift && $shift['start']->copy()->setTimezone($timezone)->toDateString() !== $shift['end']->copy()->setTimezone($timezone)->toDateString();
    }

    /**
     * The shift as it was kept on the day row: empty for a day off, null when no working hours were set.
     *
     * @return array{start: Carbon, end: Carbon, unpaid_break_minutes: int}|array{}|null
     */
    private static function keptShift(ManufactureArtisanDay $day): ?array
    {
        return match (true) {
            $day->expected_minutes === null                => null,
            !$day->shift_start || !$day->shift_end         => [],
            default                                        => [
                'start'                => $day->shift_start,
                'end'                  => $day->shift_end,
                'unpaid_break_minutes' => max(0, (int) round($day->shift_start->diffInMinutes($day->shift_end)) - $day->expected_minutes),
            ],
        };
    }

    /**
     * The floor sessions that count for hours and pay: those of the factory's own staff. Staff of another organisation and
     * staff hidden from the artisan roster (someone from another department who used a tablet once) are not artisans,
     * so a job they opened on a tablet never brings their clocked hours into the factory's days or payroll.
     *
     * @return Builder<ManufactureTaskSession>
     */
    public static function artisanSessions(Production $production): Builder
    {
        $artisans = Employee::withTrashed()
            ->where('organisation_id', $production->organisation_id)
            ->whereNotIn('id', Arr::get($production->data, 'hidden_artisan_ids', []))
            ->select('id');

        return ManufactureTaskSession::where('production_id', $production->id)
            ->where(fn (Builder $query) => $query->whereNull('employee_id')->orWhereIn('employee_id', $artisans));
    }

    /**
     * The same test as artisanSessions(), for one employee.
     */
    public static function isArtisan(Production $production, Employee $employee): bool
    {
        return $employee->organisation_id == $production->organisation_id
            && !in_array($employee->id, Arr::get($production->data, 'hidden_artisan_ids', []));
    }

    /**
     * The artisans of a period: everyone who closed a session in the production in the eight weeks up to its end, so a
     * day or a week clocked with no work on the tablet is still checked and paid.
     *
     * @return array<int, int>
     */
    public static function rosterEmployeeIds(Production $production, Carbon $from, Carbon $to): array
    {
        // ponytail: an artisan off the tablet for over eight weeks drops out; an artisan flag on the employee if that bites
        return self::artisanSessions($production)
            ->where('state', ManufactureTaskSessionStateEnum::CLOSED)
            ->whereNotNull('employee_id')
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
            ->get(['id', 'subject_id', 'date', 'working_duration', 'breaks_duration', 'number_time_trackers', 'number_open_time_trackers'])
            ->keyBy(fn (Timesheet $timesheet) => $timesheet->subject_id.'|'.$timesheet->date->toDateString());
    }

    /**
     * The first clock in and the last clock out of each timesheet.
     *
     * @param  Collection<string, Timesheet>  $clockings
     * @return array<string, array{in: Carbon|null, out: Carbon|null}> keyed by employee id|date
     */
    public static function clockTimes(Collection $clockings): array
    {
        if ($clockings->isEmpty()) {
            return [];
        }

        $times = DB::table('time_trackers')
            ->whereIn('timesheet_id', $clockings->pluck('id'))
            ->whereNull('deleted_at')
            ->groupBy('timesheet_id')
            ->selectRaw('timesheet_id, min(starts_at) as clocked_in_at, max(ends_at) as clocked_out_at')
            ->get()
            ->keyBy('timesheet_id');

        $clockTimes = [];
        foreach ($clockings as $key => $timesheet) {
            $row              = $times->get($timesheet->id);
            $clockTimes[$key] = [
                'in'  => $row?->clocked_in_at ? Carbon::parse($row->clocked_in_at) : null,
                'out' => $row?->clocked_out_at ? Carbon::parse($row->clocked_out_at) : null,
            ];
        }

        return $clockTimes;
    }

    /**
     * The shift length less the day's unpaid break; 0 on a day off, null with no work schedule.
     *
     * @param  array{start: Carbon, end: Carbon, unpaid_break_minutes: int}|array{}|null  $shift
     */
    public static function expectedMinutes(?array $shift): ?int
    {
        return match (true) {
            $shift === null => null,
            $shift === []   => 0,
            default         => max(0, (int) round($shift['start']->diffInMinutes($shift['end']) - $shift['unpaid_break_minutes'])),
        };
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
     * @param  array{in: Carbon|null, out: Carbon|null}|null  $clockTimes
     * @param  array{paid: int, outside: int, overtime: int, shift: array}|null  $paidTime  when a paid time rule limits the day to its shift
     * @param  bool  $keepsShift  a day in a locked pay week under a paid time rule: its shift stays as kept on the row
     */
    private function hydrateDay(Production $production, Employee $employee, string $date, Collection $sessions, int $tolerance, ?ManufactureArtisanDay $day, Collection $breaksByUser, ?Timesheet $timesheet, ?array $clockTimes, ?ManufactureOvertime $overtime, ?array $paidTime, bool $keepsShift = false): void
    {
        $schedule = $this->schedule($employee);
        $shift    = match (true) {
            $keepsShift && $day !== null => self::keptShift($day),
            $paidTime !== null           => $paidTime['shift'],
            default                      => $schedule ? $employee->scheduledShiftOn($date, $schedule) : null,
        };
        $expectedMinutes = self::expectedMinutes($shift);

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
            'paid_seconds'           => $paidTime['paid'] ?? null,
            'paid_overtime_seconds'  => $paidTime['overtime'] ?? null,
            'tablet_over_clocked_minutes' => $this->isDayRunning($employee, $date, $shift ?: []) ? 0 : max(0, $recordedMinutes - ($clockedMinutes ?? 0)),
            'open_clockings'         => $timesheet->number_open_time_trackers ?? 0,
            'clocked_in_at'          => $clockTimes['in'] ?? null,
            'clocked_out_at'         => $clockTimes['out'] ?? null,
            'manufacture_overtime_id' => $overtime?->id,
            'tolerance_minutes'      => $tolerance,
            'unchecked_running_jobs' => $runningJobs,
            'status'                 => $status,
        ]);
        $day->setRelation('overtime', $overtime);
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
