<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 18:00:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\WarehouseTeam\UI;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWarehouseTeamDashboard
{
    use AsObject;

    public const array PERIODS = ['today', 'yesterday', 'week', 'month'];

    private const int TREND_DAYS = 30;

    private const int USUAL_WEEKS = 4;

    private const int OPEN_TRACKER_LOOKBACK_DAYS = 14;

    private const int ABSENT_AFTER_MINUTES = 30;

    private const array BACKLOG_STAGES = [
        'to_pick' => [DeliveryNoteStateEnum::UNASSIGNED, DeliveryNoteStateEnum::QUEUED, DeliveryNoteStateEnum::HANDLING],
        'blocked' => [DeliveryNoteStateEnum::HANDLING_BLOCKED],
        'to_pack' => [DeliveryNoteStateEnum::PICKED, DeliveryNoteStateEnum::PACKING],
        'packed'  => [DeliveryNoteStateEnum::PACKED, DeliveryNoteStateEnum::FINALISED],
    ];

    private string $timezone;

    private Carbon $now;

    private Carbon $today;

    private Collection $employees;

    private array $employeeIds;

    private Collection $userIds;

    private Warehouse $warehouse;

    public function handle(Warehouse $warehouse, string $period): array
    {
        $this->warehouse = $warehouse;
        $this->timezone  = $warehouse->organisation->timezone->name ?? 'UTC';
        $this->now       = Carbon::now($this->timezone);
        $this->today     = $this->now->copy()->startOfDay();

        $this->employees = ShowWarehouseTeam::teamQuery($warehouse)
            ->with(['jobPositions' => fn ($query) => $query->where('job_positions.department', 'warehouse')->select('job_positions.id', 'job_positions.name')])
            ->orderBy('contact_name')
            ->get(['employees.id', 'employees.slug', 'employees.contact_name', 'employees.alias']);
        $this->employeeIds = $this->employees->pluck('id')->all();
        $this->userIds     = DB::table('user_has_models')
            ->where('model_type', 'Employee')
            ->whereIn('model_id', $this->employeeIds)
            ->where('status', true)
            ->pluck('user_id', 'model_id');

        [$from, $to]                 = $this->periodBounds($period, $this->today);
        $days                        = (int) $from->diffInDays($to) + 1;
        [$previousFrom, $previousTo] = [$from->copy()->subDays($days), $from->copy()->subDay()];

        $current  = $this->periodFigures($from, $to);
        $previous = $this->periodFigures($previousFrom, $previousTo);

        return [
            'period'           => $period,
            'from'             => $from->toDateString(),
            'to'               => $to->toDateString(),
            'previous_from'    => $previousFrom->toDateString(),
            'previous_to'      => $previousTo->toDateString(),
            'timezone'         => $this->timezone,
            'now'              => $this->now->toIso8601String(),
            'team_size'        => $this->employees->count(),
            'floor'            => $this->floor(),
            'backlog'          => $this->backlog(),
            'kpis'             => $this->kpis($current, $previous),
            'hourly'           => $this->hourly(),
            'daily'            => $this->daily(),
            'leaderboard'      => $this->leaderboard($from, $to, $current),
            'last_activity_at' => $this->lastActivityAt(),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function periodBounds(string $period, Carbon $today): array
    {
        return match ($period) {
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            'week'      => [$today->copy()->subDays(6), $today->copy()],
            'month'     => [$today->copy()->subDays(self::TREND_DAYS - 1), $today->copy()],
            default     => [$today->copy(), $today->copy()],
        };
    }

    private function utcRange(Carbon $fromDay, Carbon $toDay): array
    {
        return [$fromDay->copy()->startOfDay()->utc(), $toDay->copy()->endOfDay()->utc()];
    }

    private function localExpression(string $column): string
    {
        return "(($column AT TIME ZONE 'UTC') AT TIME ZONE '".addslashes($this->timezone)."')";
    }

    private function teamDeliveryNotes(string $userColumn, string $doneAtColumn, Carbon $from, Carbon $to): Builder
    {
        return DB::table('delivery_notes')
            ->where('warehouse_id', $this->warehouse->id)
            ->whereIn($userColumn, $this->userIds->values())
            ->whereBetween($doneAtColumn, $this->utcRange($from, $to))
            ->whereNull('deleted_at');
    }

    private function teamPickings(Carbon $from, Carbon $to): Builder
    {
        return DB::table('pickings')
            ->where('organisation_id', $this->warehouse->organisation_id)
            ->whereIn('picker_user_id', $this->userIds->values())
            ->whereBetween('created_at', $this->utcRange($from, $to));
    }

    private function teamTimesheets(Carbon $from, Carbon $to): Builder
    {
        return DB::table('timesheets')
            ->where('subject_type', 'Employee')
            ->whereIn('subject_id', $this->employeeIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * @return array{picks: Collection, packs: Collection, pickings: Collection, timesheets: Collection, late: Collection}
     */
    private function periodFigures(Carbon $from, Carbon $to): array
    {
        $workDone = fn (string $userColumn, string $doneAtColumn) => $this->teamDeliveryNotes($userColumn, $doneAtColumn, $from, $to)
            ->groupBy($userColumn)
            ->selectRaw("$userColumn as user_id, COUNT(*) as dns, COALESCE(SUM(number_items), 0)::int as items")
            ->get()->keyBy('user_id');

        return [
            'picks'      => $workDone('picker_user_id', 'picked_at'),
            'packs'      => $workDone('packer_user_id', 'packed_at'),
            'pickings'   => $this->teamPickings($from, $to)
                ->groupBy('picker_user_id')
                ->selectRaw("picker_user_id as user_id, COUNT(*) FILTER (WHERE type = 'pick') as lines, COUNT(*) FILTER (WHERE type = 'not-pick') as short")
                ->get()->keyBy('user_id'),
            'timesheets' => $this->teamTimesheets($from, $to)
                ->groupBy('subject_id')
                ->selectRaw('subject_id, COALESCE(SUM(working_duration), 0)::int as worked_seconds, COUNT(*) FILTER (WHERE number_time_trackers > 0) as days_worked, MIN(start_at) as first_in, MAX(end_at) as last_out')
                ->get()->keyBy('subject_id'),
            'late'       => DB::table('clockings')
                ->where('subject_type', 'Employee')
                ->whereIn('subject_id', $this->employeeIds)
                ->whereNull('deleted_at')
                ->where('is_late', true)
                ->whereBetween('clocked_at', $this->utcRange($from, $to))
                ->whereNotExists(fn ($query) => $query->from('clockings as earlier')
                    ->whereColumn('earlier.timesheet_id', 'clockings.timesheet_id')
                    ->whereNull('earlier.deleted_at')
                    ->whereColumn('earlier.clocked_at', '<', 'clockings.clocked_at'))
                ->groupBy('subject_id')
                ->selectRaw('subject_id, COUNT(*) as late')
                ->pluck('late', 'subject_id'),
        ];
    }

    private function kpis(array $current, array $previous): array
    {
        $figure = function (array $figures): array {
            $worked = (int) $figures['timesheets']->sum('worked_seconds');
            $items  = (int) $figures['picks']->sum('items') + (int) $figures['packs']->sum('items');
            $lines  = (int) $figures['pickings']->sum('lines');
            $short  = (int) $figures['pickings']->sum('short');

            return [
                'picked_dns'     => (int) $figures['picks']->sum('dns'),
                'packed_dns'     => (int) $figures['packs']->sum('dns'),
                'items'          => $items,
                'worked_seconds' => $worked,
                'people_worked'  => $figures['timesheets']->filter(fn ($row) => $row->days_worked > 0)->count(),
                'items_per_hour' => $worked >= 600 ? round($items / ($worked / 3600), 1) : null,
                'short_picks'    => $short,
                'short_rate'     => ($lines + $short) > 0 ? round(100 * $short / ($lines + $short), 1) : null,
                'late'           => (int) $figures['late']->sum(),
            ];
        };

        $now  = $figure($current);
        $then = $figure($previous);

        return collect($now)->map(fn ($value, $key) => ['value' => $value, 'previous' => $then[$key]])->all();
    }

    private function floor(): array
    {
        $todayStartUtc = $this->today->copy()->utc();

        $openTrackers = DB::table('time_trackers')
            ->where('subject_type', 'Employee')
            ->whereIn('subject_id', $this->employeeIds)
            ->whereNull('ends_at')
            ->whereNull('deleted_at')
            ->where('starts_at', '>=', $this->today->copy()->subDays(self::OPEN_TRACKER_LOOKBACK_DAYS)->utc())
            ->orderBy('starts_at')
            ->get(['subject_id', 'starts_at', 'timesheet_id']);

        $openToday    = $openTrackers->where('starts_at', '>=', $todayStartUtc->toDateTimeString())->keyBy('subject_id');
        $openPrevious = $openTrackers->where('starts_at', '<', $todayStartUtc->toDateTimeString());

        $todaySheets = $this->teamTimesheets($this->today, $this->today)
            ->get(['subject_id', 'start_at', 'end_at', 'working_duration', 'number_open_time_trackers'])
            ->keyBy('subject_id');

        $onLeave = DB::table('leaves')
            ->whereIn('employee_id', $this->employeeIds)
            ->whereNull('deleted_at')
            ->where('status', 'approved')
            ->where('start_date', '<=', $this->today->toDateString())
            ->where('end_date', '>=', $this->today->toDateString())
            ->get(['employee_id', 'type', 'is_half_day'])
            ->keyBy('employee_id');

        $schedules = $this->expectedHoursToday();

        $people = $this->employees->map(function (Employee $employee) use ($openToday, $todaySheets, $onLeave, $schedules) {
            $sheet    = $todaySheets->get($employee->id);
            $open     = $openToday->get($employee->id);
            $leave    = $onLeave->get($employee->id);
            $schedule = $schedules[$employee->id] ?? null;

            if ($sheet && $sheet->number_open_time_trackers > 0) {
                $status = 'on_site';
                $since  = $open?->starts_at ?? $sheet->start_at;
            } elseif ($sheet && $sheet->end_at) {
                $status = 'clocked_out';
                $since  = $sheet->end_at;
            } elseif ($leave) {
                $status = 'on_leave';
                $since  = null;
            } elseif ($schedule === null) {
                $status = 'day_off';
                $since  = null;
            } else {
                $status = $this->now->greaterThan($schedule['start']->copy()->addMinutes(self::ABSENT_AFTER_MINUTES)) ? 'absent' : 'expected';
                $since  = $schedule['start']->toIso8601String();
            }

            return [
                'id'             => $employee->id,
                'slug'           => $employee->slug,
                'name'           => $employee->contact_name ?: $employee->alias,
                'positions'      => $employee->jobPositions->pluck('name')->unique()->values()->all(),
                'status'         => $status,
                'since'          => $since ? Carbon::parse($since, 'UTC')->toIso8601String() : null,
                'worked_seconds' => (int) ($sheet->working_duration ?? 0),
                'leave_type'     => $leave?->type,
                'expected_end'   => $schedule ? $schedule['end']->toIso8601String() : null,
            ];
        })->values();

        $names = $this->employees->keyBy('id');

        return [
            'people'             => $people,
            'counts'             => $people->countBy('status')->all(),
            'open_previous_days' => $openPrevious->map(fn ($tracker) => [
                'employee_id' => $tracker->subject_id,
                'name'        => $names->get($tracker->subject_id)?->contact_name ?: $names->get($tracker->subject_id)?->alias,
                'started_at'  => Carbon::parse($tracker->starts_at, 'UTC')->toIso8601String(),
            ])->values(),
        ];
    }

    /**
     * Today's expected start and end per employee from their effective schedule (own default with
     * days, otherwise the organisation's). No entry means today is not a working day.
     *
     * @return array<int, array{start: Carbon, end: Carbon}>
     */
    private function expectedHoursToday(): array
    {
        $dayOfWeek = $this->today->dayOfWeekIso;

        $rows = DB::table('work_schedules')
            ->join('work_schedule_days', 'work_schedule_days.work_schedule_id', 'work_schedules.id')
            ->where('work_schedules.type', 'default')
            ->where('work_schedules.is_active', true)
            ->where(function ($query) {
                $query->where(fn ($query) => $query->where('work_schedules.schedulable_type', 'Employee')->whereIn('work_schedules.schedulable_id', $this->employeeIds))
                    ->orWhere(fn ($query) => $query->where('work_schedules.schedulable_type', 'Organisation')->where('work_schedules.schedulable_id', $this->warehouse->organisation_id));
            })
            ->get(['work_schedules.schedulable_type', 'work_schedules.schedulable_id', 'work_schedule_days.day_of_week', 'work_schedule_days.is_working_day', 'work_schedule_days.start_time', 'work_schedule_days.end_time']);

        $toHours = function (Collection $days) use ($dayOfWeek): array|false|null {
            $day = $days->firstWhere('day_of_week', $dayOfWeek);
            if (!$day || !$day->is_working_day || !$day->start_time) {
                return null;
            }

            return [
                'start' => $this->today->copy()->setTimeFromTimeString($day->start_time),
                'end'   => $this->today->copy()->setTimeFromTimeString($day->end_time ?? $day->start_time),
            ];
        };

        $organisationHours = $toHours($rows->where('schedulable_type', 'Organisation'));
        $ownDays           = $rows->where('schedulable_type', 'Employee')->groupBy('schedulable_id');

        $hours = [];
        foreach ($this->employeeIds as $employeeId) {
            $own                = $ownDays->get($employeeId);
            $hours[$employeeId] = $own ? $toHours($own) : $organisationHours;
        }

        return array_filter($hours);
    }

    private function backlog(): array
    {
        $states = array_map(fn (DeliveryNoteStateEnum $state) => $state->value, array_merge(...array_values(self::BACKLOG_STAGES)));

        $rows = DB::table('delivery_notes')
            ->where('warehouse_id', $this->warehouse->id)
            ->whereNull('deleted_at')
            ->whereIn('state', $states)
            ->groupBy('state')
            ->selectRaw('state, COUNT(*) as total, COALESCE(SUM(number_items), 0)::int as items, MIN(created_at) as oldest_at')
            ->get()->keyBy('state');

        $backlog = [];
        foreach (self::BACKLOG_STAGES as $stage => $stageStates) {
            $stageRows        = $rows->only(array_map(fn (DeliveryNoteStateEnum $state) => $state->value, $stageStates));
            $oldest           = $stageRows->pluck('oldest_at')->filter()->min();
            $backlog[$stage] = [
                'count'     => (int) $stageRows->sum('total'),
                'items'     => (int) $stageRows->sum('items'),
                'oldest_at' => $oldest ? Carbon::parse($oldest, 'UTC')->toIso8601String() : null,
            ];
        }

        return $backlog;
    }

    /**
     * Delivery notes picked and packed per hour of today, against the average of the same weekday
     * over the previous four weeks.
     */
    private function hourly(): array
    {
        $hourOf = fn (string $column) => 'EXTRACT(HOUR FROM '.$this->localExpression($column).')::int';

        $series = function (string $userColumn, string $doneAtColumn, Carbon $from, Carbon $to, bool $sameWeekday) use ($hourOf): array {
            $query = $this->teamDeliveryNotes($userColumn, $doneAtColumn, $from, $to);
            if ($sameWeekday) {
                $query->whereRaw('EXTRACT(ISODOW FROM '.$this->localExpression($doneAtColumn).') = ?', [$this->today->dayOfWeekIso]);
            }
            $byHour = $query->groupByRaw($hourOf($doneAtColumn))
                ->selectRaw($hourOf($doneAtColumn).' as hour, COUNT(*) as dns')
                ->pluck('dns', 'hour');

            return collect(range(0, 23))->map(fn ($hour) => (int) ($byHour[$hour] ?? 0))->all();
        };

        $usualFrom = $this->today->copy()->subWeeks(self::USUAL_WEEKS);
        $usualTo   = $this->today->copy()->subDay();
        $divide    = fn (array $counts) => array_map(fn ($count) => round($count / self::USUAL_WEEKS, 1), $counts);

        return [
            'hours'        => range(0, 23),
            'today_picked' => $series('picker_user_id', 'picked_at', $this->today, $this->today, false),
            'today_packed' => $series('packer_user_id', 'packed_at', $this->today, $this->today, false),
            'usual_picked' => $divide($series('picker_user_id', 'picked_at', $usualFrom, $usualTo, true)),
            'usual_packed' => $divide($series('packer_user_id', 'packed_at', $usualFrom, $usualTo, true)),
        ];
    }

    private function daily(): array
    {
        $from = $this->today->copy()->subDays(self::TREND_DAYS - 1);
        $to   = $this->today;

        $dayOf  = fn (string $column) => 'DATE('.$this->localExpression($column).')';
        $byDay  = function (string $userColumn, string $doneAtColumn) use ($from, $to, $dayOf): Collection {
            return $this->teamDeliveryNotes($userColumn, $doneAtColumn, $from, $to)
                ->groupByRaw($dayOf($doneAtColumn))
                ->selectRaw($dayOf($doneAtColumn).' as day, COUNT(*) as dns, COALESCE(SUM(number_items), 0)::int as items')
                ->get()->keyBy('day');
        };
        $picks  = $byDay('picker_user_id', 'picked_at');
        $packs  = $byDay('packer_user_id', 'packed_at');
        $worked = $this->teamTimesheets($from, $to)
            ->groupBy('date')
            ->selectRaw('date, COALESCE(SUM(working_duration), 0)::int as seconds, COUNT(*) FILTER (WHERE number_time_trackers > 0) as people')
            ->get()->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $days = collect(range(0, self::TREND_DAYS - 1))->map(fn ($offset) => $from->copy()->addDays($offset)->toDateString());

        return [
            'days'           => $days->all(),
            'picked'         => $days->map(fn ($day) => (int) ($picks[$day]->dns ?? 0))->all(),
            'packed'         => $days->map(fn ($day) => (int) ($packs[$day]->dns ?? 0))->all(),
            'worked_hours'   => $days->map(fn ($day) => round(($worked[$day]->seconds ?? 0) / 3600, 1))->all(),
            'people'         => $days->map(fn ($day) => (int) ($worked[$day]->people ?? 0))->all(),
            'items_per_hour' => $days->map(function ($day) use ($picks, $packs, $worked) {
                $seconds = (int) ($worked[$day]->seconds ?? 0);
                $items   = (int) ($picks[$day]->items ?? 0) + (int) ($packs[$day]->items ?? 0);

                return $seconds >= 600 ? round($items / ($seconds / 3600), 1) : null;
            })->all(),
        ];
    }

    private function leaderboard(Carbon $from, Carbon $to, array $figures): Collection
    {
        return $this->employees->map(function (Employee $employee) use ($figures) {
            $userId    = $this->userIds->get($employee->id);
            $timesheet = $figures['timesheets']->get($employee->id);
            $pick      = $userId ? $figures['picks']->get($userId) : null;
            $pack      = $userId ? $figures['packs']->get($userId) : null;
            $picking   = $userId ? $figures['pickings']->get($userId) : null;
            $worked    = (int) ($timesheet->worked_seconds ?? 0);
            $items     = (int) ($pick->items ?? 0) + (int) ($pack->items ?? 0);
            $lines     = (int) ($picking->lines ?? 0);
            $short     = (int) ($picking->short ?? 0);

            return [
                'id'             => $employee->id,
                'slug'           => $employee->slug,
                'name'           => $employee->contact_name ?: $employee->alias,
                'positions'      => $employee->jobPositions->pluck('name')->unique()->values()->all(),
                'has_user'       => (bool) $userId,
                'days_worked'    => (int) ($timesheet->days_worked ?? 0),
                'worked_seconds' => $worked,
                'first_in'       => $timesheet?->first_in ? Carbon::parse($timesheet->first_in, 'UTC')->toIso8601String() : null,
                'last_out'       => $timesheet?->last_out ? Carbon::parse($timesheet->last_out, 'UTC')->toIso8601String() : null,
                'late'           => (int) ($figures['late'][$employee->id] ?? 0),
                'picked_dns'     => (int) ($pick->dns ?? 0),
                'picked_items'   => (int) ($pick->items ?? 0),
                'packed_dns'     => (int) ($pack->dns ?? 0),
                'packed_items'   => (int) ($pack->items ?? 0),
                'pick_lines'     => $lines,
                'short_picks'    => $short,
                'short_rate'     => ($lines + $short) > 0 ? round(100 * $short / ($lines + $short), 1) : null,
                'items_per_hour' => $worked >= 600 ? round($items / ($worked / 3600), 1) : null,
            ];
        })->sortByDesc(fn ($row) => [$row['items_per_hour'] ?? -1, $row['picked_items'] + $row['packed_items']])->values();
    }

    private function lastActivityAt(): ?string
    {
        $last = collect([
            $this->teamDeliveryNotes('picker_user_id', 'picked_at', $this->today->copy()->subDays(self::TREND_DAYS), $this->today)->max('picked_at'),
            $this->teamDeliveryNotes('packer_user_id', 'packed_at', $this->today->copy()->subDays(self::TREND_DAYS), $this->today)->max('packed_at'),
        ])->filter()->max();

        return $last ? Carbon::parse($last, 'UTC')->toIso8601String() : null;
    }
}
