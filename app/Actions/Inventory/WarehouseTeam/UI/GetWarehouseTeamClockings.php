<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 18:00:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\WarehouseTeam\UI;

use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\Employee;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWarehouseTeamClockings
{
    use AsObject;

    private const int OPEN_TRACKER_LOOKBACK_DAYS = 14;

    /**
     * One day of the team's clockings, grouped per person, plus the clock-ins from earlier days
     * that were never closed.
     */
    public function handle(Warehouse $warehouse, Carbon $day): array
    {
        $timezone = $day->timezoneName;
        $from     = $day->copy()->startOfDay()->utc();
        $to       = $day->copy()->endOfDay()->utc();

        $employees = ShowWarehouseTeam::teamQuery($warehouse)
            ->orderBy('contact_name')
            ->get(['employees.id', 'employees.slug', 'employees.contact_name', 'employees.alias']);
        $employeeIds = $employees->pluck('id')->all();

        $clockings = Clocking::query()
            ->where('subject_type', 'Employee')
            ->whereIn('subject_id', $employeeIds)
            ->whereBetween('clocked_at', [$from, $to])
            ->with(['clockingMachine:id,name'])
            ->orderBy('clocked_at')
            ->get();

        $addedBy   = DB::table('users')->whereIn('id', $clockings->where('generator_type', 'User')->pluck('generator_id')->unique())->pluck('contact_name', 'id');
        $clockings = $clockings->groupBy('subject_id');

        $timesheets = DB::table('timesheets')
            ->where('subject_type', 'Employee')
            ->whereIn('subject_id', $employeeIds)
            ->where('date', $day->toDateString())
            ->get(['subject_id', 'working_duration', 'breaks_duration', 'number_open_time_trackers'])
            ->keyBy('subject_id');

        $openPrevious = DB::table('time_trackers')
            ->where('subject_type', 'Employee')
            ->whereIn('subject_id', $employeeIds)
            ->whereNull('ends_at')
            ->whereNull('deleted_at')
            ->whereBetween('starts_at', [Carbon::today($timezone)->subDays(self::OPEN_TRACKER_LOOKBACK_DAYS)->utc(), Carbon::today($timezone)->utc()])
            ->orderBy('starts_at')
            ->get(['subject_id', 'starts_at', 'start_clocking_id']);

        $names = $employees->keyBy('id');

        return [
            'date'               => $day->toDateString(),
            'timezone'           => $timezone,
            'people'             => $employees->map(function (Employee $employee) use ($clockings, $timesheets, $addedBy) {
                $timesheet = $timesheets->get($employee->id);

                return [
                    'id'              => $employee->id,
                    'slug'            => $employee->slug,
                    'name'            => $employee->contact_name ?: $employee->alias,
                    'worked_seconds'  => (int) ($timesheet->working_duration ?? 0),
                    'breaks_seconds'  => (int) ($timesheet->breaks_duration ?? 0),
                    'is_open'         => ($timesheet->number_open_time_trackers ?? 0) > 0,
                    'clockings'       => ($clockings->get($employee->id) ?? collect())->map(fn (Clocking $clocking) => $this->clockingRow($clocking, $addedBy))->values(),
                ];
            })->values(),
            'open_previous_days' => $openPrevious->map(fn ($tracker) => [
                'employee_id' => $tracker->subject_id,
                'name'        => $names->get($tracker->subject_id)?->contact_name ?: $names->get($tracker->subject_id)?->alias,
                'started_at'  => Carbon::parse($tracker->starts_at, 'UTC')->toIso8601String(),
                'clocking_id' => $tracker->start_clocking_id,
            ])->values(),
        ];
    }

    private function clockingRow(Clocking $clocking, Collection $addedBy): array
    {
        return [
            'id'         => $clocking->id,
            'clocked_at' => $clocking->clocked_at->toIso8601String(),
            'type'       => $clocking->type->value,
            'machine'    => $clocking->clockingMachine?->name,
            'added_by'   => $clocking->generator_type === 'User' ? $addedBy->get($clocking->generator_id) : null,
            'is_late'    => (bool) $clocking->is_late,
            'notes'      => $clocking->notes,
        ];
    }
}
