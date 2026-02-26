<?php

namespace App\Services;

use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\EmployeeLeaveBalance;
use App\Models\HumanResources\Leave;
use App\Models\HumanResources\Timesheet;
use App\Models\HumanResources\WorkSchedule;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmployeeAnalyticsService
{
    protected array $config;

    protected array $organisationSnapshotCache = [];

    public function __construct()
    {
        $this->config = config('employee-analytics', []);
    }

    public function calculateAttendanceMetrics(Employee $employee, Carbon $startDate, Carbon $endDate): array
    {
        $range = $this->getEmployeeEffectiveRange($employee, $startDate, $endDate);

        if (!$range) {
            return [
                'working_days'              => 0,
                'attendance_eligible_days'  => 0,
                'present_days'              => 0,
                'absent_days'               => 0,
                'late_clockins'             => 0,
                'early_clockouts'           => 0,
                'total_leave_days'          => 0,
                'total_working_hours'       => 0.0,
                'overtime_hours'            => 0.0,
            ];
        }

        $employeeSubjectType = $this->getEmployeeSubjectType();
        $timezone = $this->getOrganisationTimezone($employee->organisation_id);
        $scheduleMap = $this->getOrganisationScheduleMap($employee->organisation_id);

        $timesheets = Timesheet::query()
            ->where('subject_type', $employeeSubjectType)
            ->where('subject_id', $employee->id)
            ->whereBetween('date', [$range['start']->toDateString(), $range['end']->toDateString()])
            ->select('subject_id', 'date', 'start_at', 'end_at', 'number_open_time_trackers', 'working_duration')
            ->orderBy('date')
            ->get();

        $leaves = Leave::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveStatusEnum::APPROVED)
            ->where(function ($query) use ($range) {
                $query->whereBetween('start_date', [$range['start'], $range['end']])
                    ->orWhereBetween('end_date', [$range['start'], $range['end']])
                    ->orWhere(function ($subQuery) use ($range) {
                        $subQuery->where('start_date', '<=', $range['start'])
                            ->where('end_date', '>=', $range['end']);
                    });
            })
            ->select('type', 'start_date', 'end_date')
            ->get();

        $employeeMetrics = $this->buildEmployeeMetrics(
            employee: $employee,
            timesheets: $timesheets,
            leaves: $leaves,
            scheduleMap: $scheduleMap,
            timezone: $timezone,
            rangeStart: $range['start'],
            rangeEnd: $range['end']
        );

        return [
            'working_days'              => $employeeMetrics['working_days'],
            'attendance_eligible_days'  => $employeeMetrics['attendance_eligible_days'],
            'present_days'              => $employeeMetrics['present_days'],
            'absent_days'               => $employeeMetrics['absent_days'],
            'late_clockins'             => $employeeMetrics['late_clockins'],
            'early_clockouts'           => $employeeMetrics['early_clockouts'],
            'total_leave_days'          => $employeeMetrics['total_leave_days'],
            'total_working_hours'       => $employeeMetrics['total_working_hours'],
            'overtime_hours'            => $employeeMetrics['overtime_hours'],
        ];
    }

    public function calculateLeaveMetrics(Employee $employee, Carbon $startDate, Carbon $endDate): array
    {
        $range = $this->getEmployeeEffectiveRange($employee, $startDate, $endDate);
        $leaveBalance = $this->getLeaveBalance($employee);

        if (!$range) {
            return [
                'total_leave_days' => 0,
                'leave_breakdown'  => [],
                'leave_balance'    => $leaveBalance,
            ];
        }

        $scheduleMap = $this->getOrganisationScheduleMap($employee->organisation_id);
        $expectedDateKeys = $this->getExpectedWorkingDateKeys($range['start'], $range['end'], $scheduleMap);

        $leaves = Leave::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveStatusEnum::APPROVED)
            ->where(function ($query) use ($range) {
                $query->whereBetween('start_date', [$range['start'], $range['end']])
                    ->orWhereBetween('end_date', [$range['start'], $range['end']])
                    ->orWhere(function ($subQuery) use ($range) {
                        $subQuery->where('start_date', '<=', $range['start'])
                            ->where('end_date', '>=', $range['end']);
                    });
            })
            ->select('type', 'start_date', 'end_date')
            ->get();

        $leaveSummary = $this->summariseLeaveDays(
            leaves: $leaves,
            expectedDateKeys: $expectedDateKeys,
            rangeStart: $range['start'],
            rangeEnd: $range['end']
        );

        return [
            'total_leave_days' => $leaveSummary['total_leave_days'],
            'leave_breakdown'  => $leaveSummary['leave_breakdown'],
            'leave_balance'    => $leaveBalance,
        ];
    }

    public function calculateSummaryMetrics(array $attendance, array $leave): array
    {
        $workingDays = (int) ($attendance['working_days'] ?? 0);
        $leaveDays = (int) ($leave['total_leave_days'] ?? ($attendance['total_leave_days'] ?? 0));
        $eligibleDays = (int) ($attendance['attendance_eligible_days'] ?? max(0, $workingDays - $leaveDays));
        $presentDays = (int) ($attendance['present_days'] ?? 0);
        $totalHours = (float) ($attendance['total_working_hours'] ?? 0);
        $overtimeHours = (float) ($attendance['overtime_hours'] ?? 0);

        $attendancePercentage = $eligibleDays > 0
            ? round(($presentDays / $eligibleDays) * 100, 2)
            : 0.0;

        $avgDailyHours = $presentDays > 0
            ? round($totalHours / $presentDays, 2)
            : 0.0;

        $overtimeRatio = $totalHours > 0
            ? round(($overtimeHours / $totalHours) * 100, 2)
            : 0.0;

        return [
            'attendance_percentage' => $attendancePercentage,
            'avg_daily_hours'       => $avgDailyHours,
            'overtime_ratio'        => $overtimeRatio,
        ];
    }

    public function getOrganizationAnalyticsAggregated(int $organisationId, Carbon $startDate, Carbon $endDate): object|null
    {
        $snapshot = $this->getOrganisationSnapshot($organisationId, $startDate, $endDate);
        $totalEmployees = $snapshot['total_employees'];

        $avgAttendancePercentage = $snapshot['attendance_employee_count'] > 0
            ? round($snapshot['attendance_percentage_sum'] / $snapshot['attendance_employee_count'], 2)
            : null;

        $avgWorkingHours = $totalEmployees > 0
            ? round($snapshot['total_working_hours'] / $totalEmployees, 2)
            : null;

        $avgOvertimeHours = $totalEmployees > 0
            ? round($snapshot['total_overtime_hours'] / $totalEmployees, 2)
            : null;

        return (object) [
            'total_employees'          => $totalEmployees,
            'avg_attendance_percentage' => $avgAttendancePercentage,
            'avg_total_working_hours'  => $avgWorkingHours,
            'avg_overtime_hours'       => $avgOvertimeHours,
            'total_late_clockins'      => $snapshot['total_late_clockins'],
            'total_early_clockouts'    => $snapshot['total_early_clockouts'],
            'total_leave_days'         => $snapshot['total_leave_days'],
        ];
    }

    public function getEmployeeAttendanceBreakdown(int $organisationId, Carbon $startDate, Carbon $endDate, int $limit = 10): array
    {
        $snapshot = $this->getOrganisationSnapshot($organisationId, $startDate, $endDate);

        $results = collect($snapshot['employees'])
            ->map(function (array $employee): array {
                return [
                    'id'                    => $employee['id'],
                    'name'                  => $employee['name'],
                    'slug'                  => $employee['slug'],
                    'attendance_percentage' => $employee['attendance_percentage'],
                    'late_clockins'         => $employee['late_clockins'],
                    'early_clockouts'       => $employee['early_clockouts'],
                ];
            })
            ->sortByDesc('attendance_percentage')
            ->values()
            ->take($limit)
            ->all();

        return $results;
    }

    public function getTopEmployeesByLeave(int $organisationId, Carbon $startDate, Carbon $endDate, int $limit = 10): array
    {
        $snapshot = $this->getOrganisationSnapshot($organisationId, $startDate, $endDate);

        $results = collect($snapshot['employees'])
            ->filter(fn (array $employee): bool => $employee['total_leave_days'] > 0)
            ->map(function (array $employee): array {
                return [
                    'id'               => $employee['id'],
                    'name'             => $employee['name'],
                    'slug'             => $employee['slug'],
                    'total_leave_days' => $employee['total_leave_days'],
                    'leave_breakdown'  => $employee['leave_breakdown'],
                ];
            })
            ->sortByDesc('total_leave_days')
            ->values()
            ->take($limit)
            ->all();

        return $results;
    }

    public function getEmployeeAnalytics(Employee $employee, Carbon $startDate, Carbon $endDate): ?object
    {
        return DB::table('employee_analytics')
            ->where('employee_id', $employee->id)
            ->where('period_start', $startDate->format('Y-m-d'))
            ->where('period_end', $endDate->format('Y-m-d'))
            ->first();
    }

    protected function getOrganisationSnapshot(int $organisationId, Carbon $startDate, Carbon $endDate): array
    {
        $rangeStart = $startDate->copy()->startOfDay();
        $rangeEnd = $endDate->copy()->endOfDay();
        $cacheKey = $organisationId.'|'.$rangeStart->toDateString().'|'.$rangeEnd->toDateString();

        if (isset($this->organisationSnapshotCache[$cacheKey])) {
            return $this->organisationSnapshotCache[$cacheKey];
        }

        $employees = Employee::query()
            ->where('organisation_id', $organisationId)
            ->where('state', 'working')
            ->select('id', 'contact_name', 'slug', 'employment_start_at', 'employment_end_at')
            ->get();

        $totalEmployees = $employees->count();

        if ($totalEmployees === 0) {
            $snapshot = [
                'total_employees'           => 0,
                'attendance_percentage_sum' => 0.0,
                'attendance_employee_count' => 0,
                'total_working_hours'       => 0.0,
                'total_overtime_hours'      => 0.0,
                'total_late_clockins'       => 0,
                'total_early_clockouts'     => 0,
                'total_leave_days'          => 0,
                'employees'                 => [],
            ];

            $this->organisationSnapshotCache[$cacheKey] = $snapshot;

            return $snapshot;
        }

        $timezone = $this->getOrganisationTimezone($organisationId);
        $scheduleMap = $this->getOrganisationScheduleMap($organisationId);
        $employeeSubjectType = $this->getEmployeeSubjectType();
        $employeeIds = $employees->pluck('id');

        $timesheetsByEmployee = Timesheet::query()
            ->whereIn('subject_id', $employeeIds)
            ->where('subject_type', $employeeSubjectType)
            ->whereBetween('date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->select('subject_id', 'date', 'start_at', 'end_at', 'number_open_time_trackers', 'working_duration')
            ->orderBy('subject_id')
            ->orderBy('date')
            ->get()
            ->groupBy('subject_id');

        $leavesByEmployee = Leave::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', LeaveStatusEnum::APPROVED)
            ->where(function ($query) use ($rangeStart, $rangeEnd) {
                $query->whereBetween('start_date', [$rangeStart, $rangeEnd])
                    ->orWhereBetween('end_date', [$rangeStart, $rangeEnd])
                    ->orWhere(function ($subQuery) use ($rangeStart, $rangeEnd) {
                        $subQuery->where('start_date', '<=', $rangeStart)
                            ->where('end_date', '>=', $rangeEnd);
                    });
            })
            ->select('employee_id', 'type', 'start_date', 'end_date')
            ->get()
            ->groupBy('employee_id');

        $employeeMetrics = [];
        $attendancePercentageSum = 0.0;
        $attendanceEmployeeCount = 0;
        $totalWorkingHours = 0.0;
        $totalOvertimeHours = 0.0;
        $totalLateClockins = 0;
        $totalEarlyClockouts = 0;
        $totalLeaveDays = 0;

        foreach ($employees as $employee) {
            $metrics = $this->buildEmployeeMetrics(
                employee: $employee,
                timesheets: $timesheetsByEmployee->get($employee->id, collect()),
                leaves: $leavesByEmployee->get($employee->id, collect()),
                scheduleMap: $scheduleMap,
                timezone: $timezone,
                rangeStart: $rangeStart,
                rangeEnd: $rangeEnd
            );

            $employeeMetrics[] = $metrics;
            $totalWorkingHours += $metrics['total_working_hours'];
            $totalOvertimeHours += $metrics['overtime_hours'];
            $totalLateClockins += $metrics['late_clockins'];
            $totalEarlyClockouts += $metrics['early_clockouts'];
            $totalLeaveDays += $metrics['total_leave_days'];

            if ($metrics['attendance_eligible_days'] > 0) {
                $attendancePercentageSum += $metrics['attendance_percentage'];
                $attendanceEmployeeCount++;
            }
        }

        $snapshot = [
            'total_employees'           => $totalEmployees,
            'attendance_percentage_sum' => $attendancePercentageSum,
            'attendance_employee_count' => $attendanceEmployeeCount,
            'total_working_hours'       => $totalWorkingHours,
            'total_overtime_hours'      => $totalOvertimeHours,
            'total_late_clockins'       => $totalLateClockins,
            'total_early_clockouts'     => $totalEarlyClockouts,
            'total_leave_days'          => $totalLeaveDays,
            'employees'                 => $employeeMetrics,
        ];

        $this->organisationSnapshotCache[$cacheKey] = $snapshot;

        return $snapshot;
    }

    protected function buildEmployeeMetrics(
        object $employee,
        Collection $timesheets,
        Collection $leaves,
        ?array $scheduleMap,
        string $timezone,
        Carbon $rangeStart,
        Carbon $rangeEnd
    ): array {
        $effectiveRange = $this->getEmployeeEffectiveRange($employee, $rangeStart, $rangeEnd);

        if (!$effectiveRange) {
            return [
                'id'                       => (int) $employee->id,
                'name'                     => (string) $employee->contact_name,
                'slug'                     => (string) $employee->slug,
                'working_days'             => 0,
                'attendance_eligible_days' => 0,
                'present_days'             => 0,
                'absent_days'              => 0,
                'attendance_percentage'    => 0.0,
                'late_clockins'            => 0,
                'early_clockouts'          => 0,
                'total_working_hours'      => 0.0,
                'overtime_hours'           => 0.0,
                'total_leave_days'         => 0,
                'leave_breakdown'          => [],
            ];
        }

        $expectedDateKeys = $this->getExpectedWorkingDateKeys(
            $effectiveRange['start'],
            $effectiveRange['end'],
            $scheduleMap
        );

        $workingDays = count($expectedDateKeys);
        $presentDays = $this->extractPresentDays($timesheets, $expectedDateKeys);

        $leaveSummary = $this->summariseLeaveDays(
            leaves: $leaves,
            expectedDateKeys: $expectedDateKeys,
            rangeStart: $effectiveRange['start'],
            rangeEnd: $effectiveRange['end']
        );

        $totalLeaveDays = $leaveSummary['total_leave_days'];
        $attendanceEligibleDays = max(0, $workingDays - $totalLeaveDays);
        $absentDays = max(0, $attendanceEligibleDays - $presentDays);

        [$lateClockins, $earlyClockouts] = $this->countLateAndEarly(
            timesheets: $timesheets,
            scheduleMap: $scheduleMap,
            timezone: $timezone
        );

        $totalWorkingHours = round(((int) $timesheets->sum('working_duration')) / 3600, 2);
        $overtimeHours = round($this->calculateOvertimeHours($totalWorkingHours, $attendanceEligibleDays), 2);

        $attendancePercentage = $attendanceEligibleDays > 0
            ? round(($presentDays / $attendanceEligibleDays) * 100, 2)
            : 0.0;

        return [
            'id'                       => (int) $employee->id,
            'name'                     => (string) $employee->contact_name,
            'slug'                     => (string) $employee->slug,
            'working_days'             => $workingDays,
            'attendance_eligible_days' => $attendanceEligibleDays,
            'present_days'             => $presentDays,
            'absent_days'              => $absentDays,
            'attendance_percentage'    => $attendancePercentage,
            'late_clockins'            => $lateClockins,
            'early_clockouts'          => $earlyClockouts,
            'total_working_hours'      => $totalWorkingHours,
            'overtime_hours'           => $overtimeHours,
            'total_leave_days'         => $totalLeaveDays,
            'leave_breakdown'          => $leaveSummary['leave_breakdown'],
        ];
    }

    protected function getEmployeeEffectiveRange(object $employee, Carbon $startDate, Carbon $endDate): ?array
    {
        $effectiveStart = $startDate->copy()->startOfDay();
        $effectiveEnd = $endDate->copy()->endOfDay();

        if (!empty($employee->employment_start_at)) {
            $employmentStart = Carbon::parse($employee->employment_start_at)->startOfDay();
            if ($employmentStart->gt($effectiveStart)) {
                $effectiveStart = $employmentStart;
            }
        }

        if (!empty($employee->employment_end_at)) {
            $employmentEnd = Carbon::parse($employee->employment_end_at)->endOfDay();
            if ($employmentEnd->lt($effectiveEnd)) {
                $effectiveEnd = $employmentEnd;
            }
        }

        if ($effectiveStart->gt($effectiveEnd)) {
            return null;
        }

        return [
            'start' => $effectiveStart,
            'end'   => $effectiveEnd,
        ];
    }

    protected function getExpectedWorkingDateKeys(Carbon $startDate, Carbon $endDate, ?array $scheduleMap): array
    {
        $keys = [];
        $current = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->startOfDay();

        while ($current->lte($end)) {
            if ($scheduleMap) {
                $daySchedule = $scheduleMap[$current->dayOfWeekIso] ?? null;
                if ($daySchedule && $daySchedule->is_working_day) {
                    $keys[$current->format('Y-m-d')] = true;
                }
            } else {
                if (!$current->isWeekend()) {
                    $keys[$current->format('Y-m-d')] = true;
                }
            }

            $current->addDay();
        }

        return $keys;
    }

    protected function extractPresentDays(Collection $timesheets, array $expectedDateKeys): int
    {
        if (empty($expectedDateKeys)) {
            return 0;
        }

        $presentDateKeys = [];

        foreach ($timesheets as $timesheet) {
            $timesheetDate = $timesheet->date instanceof Carbon
                ? $timesheet->date->copy()
                : Carbon::parse($timesheet->date);

            $dateKey = $timesheetDate->format('Y-m-d');
            if (isset($expectedDateKeys[$dateKey])) {
                $presentDateKeys[$dateKey] = true;
            }
        }

        return count($presentDateKeys);
    }

    protected function summariseLeaveDays(
        Collection $leaves,
        array $expectedDateKeys,
        Carbon $rangeStart,
        Carbon $rangeEnd
    ): array {
        if (empty($expectedDateKeys) || $leaves->isEmpty()) {
            return [
                'total_leave_days' => 0,
                'leave_breakdown'  => [],
            ];
        }

        $leaveDateKeys = [];
        $breakdownDateKeys = [];

        foreach ($leaves as $leave) {
            $leaveStart = Carbon::parse($leave->start_date)->startOfDay();
            $leaveEnd = Carbon::parse($leave->end_date)->endOfDay();

            if ($leaveEnd->lt($rangeStart) || $leaveStart->gt($rangeEnd)) {
                continue;
            }

            $current = $leaveStart->gt($rangeStart)
                ? $leaveStart->copy()
                : $rangeStart->copy();

            $last = $leaveEnd->lt($rangeEnd)
                ? $leaveEnd->copy()
                : $rangeEnd->copy();

            $leaveType = $leave->type->value ?? (string) $leave->type;

            while ($current->lte($last)) {
                $dateKey = $current->format('Y-m-d');
                if (isset($expectedDateKeys[$dateKey])) {
                    $leaveDateKeys[$dateKey] = true;
                    $breakdownDateKeys[$leaveType][$dateKey] = true;
                }

                $current->addDay();
            }
        }

        $leaveBreakdown = [];
        foreach ($breakdownDateKeys as $leaveType => $leaveDates) {
            $leaveBreakdown[$leaveType] = count($leaveDates);
        }

        return [
            'total_leave_days' => count($leaveDateKeys),
            'leave_breakdown'  => $leaveBreakdown,
        ];
    }

    protected function countLateAndEarly(Collection $timesheets, ?array $scheduleMap, string $timezone): array
    {
        if (!$scheduleMap) {
            return [0, 0];
        }

        $lateClockins = 0;
        $earlyClockouts = 0;

        foreach ($timesheets as $timesheet) {
            $timesheetDate = $timesheet->date instanceof Carbon
                ? $timesheet->date->copy()
                : Carbon::parse($timesheet->date);

            $daySchedule = $scheduleMap[$timesheetDate->dayOfWeekIso] ?? null;
            if (!$daySchedule || !$daySchedule->is_working_day) {
                continue;
            }

            $startAt = $timesheet->start_at?->copy()->setTimezone($timezone);
            $endAt = $timesheet->end_at?->copy()->setTimezone($timezone);

            $scheduledStart = null;
            $scheduledEnd = null;

            if ($startAt && $daySchedule->start_time && $daySchedule->end_time) {
                $scheduledStart = $startAt->copy()->setTimeFromTimeString($daySchedule->start_time);
                $scheduledEnd = $startAt->copy()->setTimeFromTimeString($daySchedule->end_time);
            }

            if ($scheduledStart && $startAt && $startAt->gt($scheduledStart->copy()->addMinutes(1))) {
                $lateClockins++;
            }

            if (
                $scheduledEnd
                && (int) ($timesheet->number_open_time_trackers ?? 0) === 0
                && $endAt
                && $endAt->lt($scheduledEnd->copy()->subMinutes(1))
            ) {
                $earlyClockouts++;
            }
        }

        return [$lateClockins, $earlyClockouts];
    }

    protected function calculateOvertimeHours(float $totalWorkingHours, int $workingDays): float
    {
        $dailyScheduledHours = $this->config['thresholds']['daily_scheduled_hours'] ?? 8.0;

        $scheduledHours = $workingDays * $dailyScheduledHours;

        return max(0, $totalWorkingHours - $scheduledHours);
    }

    protected function getLeaveBalance(Employee $employee): array
    {
        $currentYear = now()->year;

        $balance = EmployeeLeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('year', $currentYear)
            ->first();

        if (!$balance) {
            return [
                'annual_remaining'  => 0,
                'medical_remaining' => 0,
                'unpaid_remaining'  => 0,
            ];
        }

        return [
            'annual_remaining'  => $balance->annual_remaining,
            'medical_remaining' => $balance->medical_remaining,
            'unpaid_remaining'  => $balance->unpaid_remaining,
        ];
    }

    protected function getOrganisationScheduleMap(int $organisationId): ?array
    {
        $schedule = WorkSchedule::query()
            ->where('schedulable_type', 'Organisation')
            ->where('schedulable_id', $organisationId)
            ->where('is_active', true)
            ->with('days')
            ->first();

        if (!$schedule) {
            return null;
        }

        return $schedule->days->keyBy('day_of_week')->all();
    }

    protected function getOrganisationTimezone(int $organisationId): string
    {
        $organisation = Organisation::query()
            ->with('timezone')
            ->find($organisationId);

        return $organisation?->timezone?->name ?? 'UTC';
    }

    protected function getEmployeeSubjectType(): string
    {
        return (new Employee())->getMorphClass();
    }
}
