<?php

namespace App\Actions\HumanResources\Leave\Traits;

use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Enums\HumanResources\Leave\LeaveTypeEnum;
use App\Models\HumanResources\Leave;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

trait WithLeaveCalendarData
{
    protected function getCalendarData(array $filters): array
    {
        $query = Leave::query()
            ->where('organisation_id', $this->organisation->id)
            ->with(['employee.jobPositions', 'leaveType'])
            ->orderBy('employee_name')
            ->orderBy('start_date')
            ->orderBy('end_date');

        $this->applyCalendarFilters($query, $filters);

        $leaves = $query->get();

        $calendarData = [];

        foreach ($leaves as $leave) {
            if (!$leave->employee) {
                continue;
            }

            $employeeId = $leave->employee_id;
            $primaryJobPosition = $leave->employee->jobPositions->first();

            if (!isset($calendarData[$employeeId])) {
                $calendarData[$employeeId] = [
                    'id' => $leave->employee->id,
                    'name' => $leave->employee->contact_name ?? $leave->employee->alias,
                    'department' => $primaryJobPosition?->department ?? $leave->employee->department,
                    'job_title' => $primaryJobPosition?->name ?? $leave->employee->job_title,
                    'leaves' => [],
                ];
            }

            $calendarData[$employeeId]['leaves'][] = $this->formatCalendarLeave($leave);
        }

        return array_values($calendarData);
    }

    protected function generateCalendarWeeks(array $filters): array
    {
        $range = $this->getCalendarRange($filters);

        $weeks = [];
        $currentWeek = [];
        $currentDate = $range['start']->copy();
        $weekIndex = 0;

        while ($currentDate->lte($range['end'])) {
            $currentWeek[] = [
                'date' => $currentDate->format('Y-m-d'),
                'day' => $currentDate->day,
                'day_of_month' => $currentDate->day,
                'isCurrentMonth' => $currentDate->month === (int) $filters['month'],
                'is_current_month' => $currentDate->month === (int) $filters['month'],
                'isToday' => $currentDate->isToday(),
                'is_today' => $currentDate->isToday(),
                'isWeekend' => $currentDate->isWeekend(),
                'is_weekend' => $currentDate->isWeekend(),
                'week_index' => $weekIndex,
            ];

            if (count($currentWeek) === 7 || $currentDate->equalTo($range['end'])) {
                $weeks[] = [
                    'week_index' => $weekIndex,
                    'start' => $currentWeek[0]['date'],
                    'end' => $currentWeek[count($currentWeek) - 1]['date'],
                    'days' => $currentWeek,
                ];
                $currentWeek = [];
                $weekIndex++;
            }

            $currentDate->addDay();
        }

        return $weeks;
    }

    protected function getHolidays(array $filters): array
    {
        $range = $this->getCalendarRange($filters);

        try {
            $holidays = $this->organisation->holidays()
                ->forDateRange($range['start'], $range['end'])
                ->get(['name', 'from', 'to']);

            return $holidays->map(function ($holiday) {
                return [
                    'name' => $holiday->name,
                    'from' => $holiday->from->format('Y-m-d'),
                    'to' => $holiday->to->format('Y-m-d'),
                ];
            })->toArray();
        } catch (\Exception $e) {
            logger('Error getting leave calendar holidays: ' . $e->getMessage());

            return [];
        }
    }

    protected function getVisibleRange(array $filters): array
    {
        $range = $this->getCalendarRange($filters);

        return [
            'start' => $range['start']->format('Y-m-d'),
            'end' => $range['end']->format('Y-m-d'),
        ];
    }

    protected function applyTeamScope(array &$filters): void
    {
        if ($this->canEdit) {
            return;
        }

        $user = request()->user();
        $userEmployee = $user->employees()
            ->where('organisation_id', $this->organisation->id)
            ->first();

        if (!$userEmployee) {
            return;
        }

        $userJobPositions = $userEmployee->jobPositions;

        if ($userJobPositions->isEmpty()) {
            return;
        }

        $departments = $userJobPositions->pluck('department')->filter()->unique()->values()->all();
        $teams = $userJobPositions->pluck('team')->filter()->unique()->values()->all();

        if (!empty($departments)) {
            $filters['departments'] = $departments;
        }

        if (!empty($teams)) {
            $filters['teams'] = $teams;
        }
    }

    protected function applyCalendarFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['from'])) {
            $query->where('end_date', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('start_date', '<=', $filters['to']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (!empty($filters['department'])) {
            $query->whereHas('employee', function (Builder $employeeQuery) use ($filters) {
                $employeeQuery->where('department', $filters['department']);
            });
        }

        if (!empty($filters['team'])) {
            $query->whereHas('employee', function (Builder $employeeQuery) use ($filters) {
                $employeeQuery->where('team', $filters['team']);
            });
        }

        if (!empty($filters['departments'])) {
            $query->whereHas('employee', function (Builder $employeeQuery) use ($filters) {
                $employeeQuery->whereIn('department', $filters['departments']);
            });
        }

        if (!empty($filters['teams'])) {
            $query->whereHas('employee', function (Builder $employeeQuery) use ($filters) {
                $employeeQuery->whereIn('team', $filters['teams']);
            });
        }
    }

    private function getCalendarRange(array $filters): array
    {
        $year = (int) ($filters['year'] ?? Carbon::now()->year);
        $month = (int) ($filters['month'] ?? Carbon::now()->month);

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        if (!empty($filters['from'])) {
            $startDate = Carbon::parse($filters['from'])->startOfDay();
        }

        if (!empty($filters['to'])) {
            $endDate = Carbon::parse($filters['to'])->startOfDay();
        }

        return [
            'start' => $startDate,
            'end' => $endDate,
        ];
    }

    private function formatCalendarLeave(Leave $leave): array
    {
        $typeCode = is_string($leave->type) ? $leave->type : null;
        $status = $leave->status?->value ?? (string) $leave->status;
        $startDate = $leave->start_date?->copy()->startOfDay();
        $endDate = $leave->end_date?->copy()->startOfDay();

        return [
            'id' => $leave->id,
            'employee_name' => $leave->employee_name,
            'type' => $typeCode,
            'type_label' => $leave->leaveType?->name
                ?? LeaveTypeEnum::labels()[$typeCode] ?? Str::of((string) $typeCode)->replace('-', ' ')->title()->toString(),
            'type_code' => $leave->leaveType?->short_code
                ?? LeaveTypeEnum::shortCodes()[$typeCode] ?? Str::upper(Str::substr((string) $typeCode, 0, 2)),
            'type_color' => $this->resolveLeaveColor($leave->leaveType?->color, $typeCode),
            'start_date' => $startDate?->format('Y-m-d'),
            'end_date' => $endDate?->format('Y-m-d'),
            'duration_days' => $leave->duration_days ?: ($startDate && $endDate ? $startDate->diffInDays($endDate) + 1 : 0),
            'working_days' => $startDate && $endDate ? $this->calculateWorkingDays($startDate, $endDate) : 0,
            'reason' => $leave->reason,
            'status' => $status,
            'status_label' => LeaveStatusEnum::labels()[$status] ?? Str::headline($status),
        ];
    }

    private function calculateWorkingDays(Carbon $startDate, Carbon $endDate): int
    {
        $workingDays = 0;
        $cursor = $startDate->copy();

        while ($cursor->lte($endDate)) {
            if (!$cursor->isWeekend()) {
                $workingDays++;
            }

            $cursor->addDay();
        }

        return $workingDays;
    }

    private function resolveLeaveColor(?string $color, ?string $typeCode): string
    {
        $normalizedColor = strtolower(trim((string) $color));

        if (Str::startsWith($normalizedColor, '#')) {
            return $normalizedColor;
        }

        $palette = [
            'green' => '#16A34A',
            'orange' => '#EA580C',
            'black' => '#111827',
            'purple' => '#7C3AED',
            'pink' => '#DB2777',
            'cyan' => '#0891B2',
            'indigo' => '#4F46E5',
            'gray' => '#6B7280',
            'grey' => '#6B7280',
            'red' => '#DC2626',
            'yellow' => '#D97706',
            'blue' => '#2563EB',
        ];

        if (isset($palette[$normalizedColor])) {
            return $palette[$normalizedColor];
        }

        $bucket = LeaveTypeEnum::colors()[$typeCode] ?? 'indigo';

        return $palette[$bucket] ?? '#4F46E5';
    }
}
