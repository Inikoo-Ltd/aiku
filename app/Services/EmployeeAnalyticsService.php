<?php

namespace App\Services;

use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\EmployeeLeaveBalance;
use App\Models\HumanResources\Leave;
use App\Models\HumanResources\Timesheet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeAnalyticsService
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('employee-analytics', []);
    }

    public function calculateAttendanceMetrics(Employee $employee, Carbon $startDate, Carbon $endDate): array
    {
        $workingDays = $this->calculateWorkingDays($startDate, $endDate);

        $presentDays = $this->calculatePresentDays($employee, $startDate, $endDate);

        $approvedLeaveDays = $this->getApprovedLeaveDays($employee, $startDate, $endDate);

        $absentDays = max(0, $workingDays - $presentDays - $approvedLeaveDays);

        $lateClockins = $this->calculateLateClockins($employee, $startDate, $endDate);

        $earlyClockouts = $this->calculateEarlyClockouts($employee, $startDate, $endDate);

        $totalWorkingHours = $this->calculateTotalWorkingHours($employee, $startDate, $endDate);

        $overtimeHours = $this->calculateOvertimeHours($totalWorkingHours, $workingDays);

        return [
            'working_days'        => $workingDays,
            'present_days'        => $presentDays,
            'absent_days'         => $absentDays,
            'late_clockins'       => $lateClockins,
            'early_clockouts'     => $earlyClockouts,
            'total_working_hours' => round($totalWorkingHours, 2),
            'overtime_hours'      => round($overtimeHours, 2),
        ];
    }

    public function calculateLeaveMetrics(Employee $employee, Carbon $startDate, Carbon $endDate): array
    {
        $leaves = Leave::where('employee_id', $employee->id)
            ->where('status', LeaveStatusEnum::APPROVED)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->get();

        $totalLeaveDays = 0;
        $leaveBreakdown = [];

        foreach ($leaves as $leave) {
            $leaveStart = Carbon::parse($leave->start_date)->max($startDate);
            $leaveEnd = Carbon::parse($leave->end_date)->min($endDate);

            $daysInPeriod = $this->calculateWorkingDays($leaveStart, $leaveEnd);
            $totalLeaveDays += $daysInPeriod;

            $type = $leave->type->value;
            if (!isset($leaveBreakdown[$type])) {
                $leaveBreakdown[$type] = 0;
            }
            $leaveBreakdown[$type] += $daysInPeriod;
        }

        $leaveBalance = $this->getLeaveBalance($employee);

        return [
            'total_leave_days' => $totalLeaveDays,
            'leave_breakdown'  => $leaveBreakdown,
            'leave_balance'    => $leaveBalance,
        ];
    }

    public function calculateSummaryMetrics(array $attendance, array $leave): array
    {
        $workingDays = $attendance['working_days'] ?? 0;
        $presentDays = $attendance['present_days'] ?? 0;
        $totalHours = $attendance['total_working_hours'] ?? 0;
        $overtimeHours = $attendance['overtime_hours'] ?? 0;

        $attendancePercentage = $workingDays > 0
            ? round(($presentDays / $workingDays) * 100, 2)
            : 0;

        $avgDailyHours = $presentDays > 0
            ? round($totalHours / $presentDays, 2)
            : 0;

        $overtimeRatio = $totalHours > 0
            ? round(($overtimeHours / $totalHours) * 100, 2)
            : 0;

        return [
            'attendance_percentage' => $attendancePercentage,
            'avg_daily_hours'       => $avgDailyHours,
            'overtime_ratio'        => $overtimeRatio,
        ];
    }

    public function getOrganizationAnalyticsAggregated(int $organisationId, Carbon $startDate, Carbon $endDate): object|null
    {
        $workingDays = $this->calculateWorkingDays($startDate, $endDate);

        $employeeIds = DB::table('employees')
            ->where('organisation_id', $organisationId)
            ->where('state', 'working')
            ->pluck('id');

        $totalEmployees = $employeeIds->count();

        $totalLeaveDays = Leave::query()
            ->where('organisation_id', $organisationId)
            ->where('status', LeaveStatusEnum::APPROVED)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->sum('duration_days');

        $totalLateClockins = $this->calculateAggregatedLateClockins($organisationId, $startDate, $endDate);
        $totalEarlyClockouts = $this->calculateAggregatedEarlyClockouts($organisationId, $startDate, $endDate);
        $avgAttendancePercentage = $this->calculateAggregatedAttendancePercentage($organisationId, $startDate, $endDate, $workingDays);

        $totalWorkingHours = $this->calculateAggregatedWorkingHours($organisationId, $startDate, $endDate);
        $avgWorkingHours = $totalEmployees > 0 ? round($totalWorkingHours / $totalEmployees, 2) : null;

        $avgOvertimeHours = $this->calculateAggregatedOvertimeHours($organisationId, $startDate, $endDate, $workingDays, $totalEmployees);

        return (object) [
            'total_employees' => $totalEmployees,
            'avg_attendance_percentage' => $avgAttendancePercentage,
            'avg_total_working_hours' => $avgWorkingHours,
            'avg_overtime_hours' => $avgOvertimeHours,
            'total_late_clockins' => $totalLateClockins,
            'total_early_clockouts' => $totalEarlyClockouts,
            'total_leave_days' => $totalLeaveDays,
        ];
    }

    public function getEmployeeAttendanceBreakdown(int $organisationId, Carbon $startDate, Carbon $endDate, int $limit = 10): array
    {
        $workingDays = $this->calculateWorkingDays($startDate, $endDate);
        $lateGraceMinutes = $this->config['thresholds']['late_grace_minutes'] ?? 15;
        $earlyDepartureMinutes = $this->config['thresholds']['early_departure_minutes'] ?? 15;
        $workStartTime = $this->config['defaults']['work_start_time'] ?? '08:00:00';
        $workEndTime = $this->config['defaults']['work_end_time'] ?? '17:00:00';

        $employees = DB::table('employees')
            ->where('organisation_id', $organisationId)
            ->where('state', 'working')
            ->select('id', 'contact_name', 'slug')
            ->get();

        $employeeIds = $employees->pluck('id');

        $clockings = Clocking::query()
            ->whereIn('subject_id', $employeeIds)
            ->where('subject_type', Employee::class)
            ->whereBetween('clocked_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->select('subject_id', 'clocked_at')
            ->orderBy('subject_id')
            ->orderBy('clocked_at')
            ->get();

        $clockingsByEmployee = $clockings->groupBy('subject_id');

        $results = [];
        foreach ($employees as $employee) {
            $employeeClockings = $clockingsByEmployee->get($employee->id, collect());

            $presentDays = $employeeClockings
                ->map(fn ($c) => $c->clocked_at->format('Y-m-d'))
                ->unique()
                ->count();

            $attendancePercentage = $workingDays > 0 ? round(($presentDays / $workingDays) * 100, 2) : 0;

            $lateClockins = 0;
            $earlyClockouts = 0;
            $processedDays = [];

            $sortedClockings = $employeeClockings->sortBy('clocked_at');
            foreach ($sortedClockings as $clocking) {
                $dateKey = $clocking->clocked_at->format('Y-m-d');

                if (!isset($processedDays[$dateKey])) {
                    $dateStr = $clocking->clocked_at->format('Y-m-d');
                    $scheduledStart = Carbon::parse($dateStr . ' ' . $workStartTime);
                    $gracePeriod = $scheduledStart->copy()->addMinutes($lateGraceMinutes);

                    if ($clocking->clocked_at->gt($gracePeriod)) {
                        $lateClockins++;
                    }
                    $processedDays[$dateKey] = ['first' => $clocking->clocked_at];
                }
            }

            $processedDays = [];
            $sortedClockingsDesc = $employeeClockings->sortByDesc('clocked_at');
            foreach ($sortedClockingsDesc as $clocking) {
                $dateKey = $clocking->clocked_at->format('Y-m-d');

                if (!isset($processedDays[$dateKey])) {
                    $dateStr = $clocking->clocked_at->format('Y-m-d');
                    $scheduledEnd = Carbon::parse($dateStr . ' ' . $workEndTime);
                    $earlyThreshold = $scheduledEnd->copy()->subMinutes($earlyDepartureMinutes);

                    if ($clocking->clocked_at->lt($earlyThreshold)) {
                        $earlyClockouts++;
                    }
                    $processedDays[$dateKey] = true;
                }
            }

            $results[] = [
                'id' => $employee->id,
                'name' => $employee->contact_name,
                'slug' => $employee->slug,
                'attendance_percentage' => $attendancePercentage,
                'late_clockins' => $lateClockins,
                'early_clockouts' => $earlyClockouts,
            ];
        }

        usort($results, fn ($a, $b) => $b['attendance_percentage'] <=> $a['attendance_percentage']);

        return array_slice($results, 0, $limit);
    }

    public function getTopEmployeesByLeave(int $organisationId, Carbon $startDate, Carbon $endDate, int $limit = 10): array
    {
        $results = Leave::query()
            ->join('employees', 'leaves.employee_id', '=', 'employees.id')
            ->where('leaves.organisation_id', $organisationId)
            ->where('leaves.status', LeaveStatusEnum::APPROVED)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('leaves.start_date', [$startDate, $endDate])
                    ->orWhereBetween('leaves.end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('leaves.start_date', '<=', $startDate)
                            ->where('leaves.end_date', '>=', $endDate);
                    });
            })
            ->select([
                'employees.id',
                'employees.contact_name',
                'employees.slug',
                DB::raw('SUM(leaves.duration_days) as total_leave_days'),
                'leaves.type',
            ])
            ->groupBy('employees.id', 'employees.contact_name', 'employees.slug', 'leaves.type')
            ->orderByDesc('total_leave_days')
            ->limit($limit)
            ->get();

        $employees = [];
        foreach ($results as $row) {
            $empId = $row->id;
            if (!isset($employees[$empId])) {
                $employees[$empId] = [
                    'id' => $row->id,
                    'name' => $row->contact_name,
                    'slug' => $row->slug,
                    'total_leave_days' => 0,
                    'leave_breakdown' => [],
                ];
            }
            $employees[$empId]['total_leave_days'] += $row->total_leave_days;
            $typeValue = $row->type->value ?? $row->type;
            $employees[$empId]['leave_breakdown'][$typeValue] =
                ($employees[$empId]['leave_breakdown'][$typeValue] ?? 0) + $row->total_leave_days;
        }

        usort($employees, fn ($a, $b) => $b['total_leave_days'] <=> $a['total_leave_days']);

        return array_slice($employees, 0, $limit);
    }

    public function getEmployeeAnalytics(Employee $employee, Carbon $startDate, Carbon $endDate): ?object
    {
        return DB::table('employee_analytics')
            ->where('employee_id', $employee->id)
            ->where('period_start', $startDate->format('Y-m-d'))
            ->where('period_end', $endDate->format('Y-m-d'))
            ->first();
    }

    protected function calculateWorkingDays(Carbon $startDate, Carbon $endDate): int
    {
        $workingDays = 0;
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            if (!$current->isWeekend()) {
                $workingDays++;
            }
            $current->addDay();
        }

        return $workingDays;
    }

    protected function calculatePresentDays(Employee $employee, Carbon $startDate, Carbon $endDate): int
    {
        return (int) Clocking::where('subject_type', Employee::class)
            ->where('subject_id', $employee->id)
            ->whereBetween('clocked_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->distinct()
            ->count(DB::raw('DATE(clocked_at)'));
    }

    protected function getApprovedLeaveDays(Employee $employee, Carbon $startDate, Carbon $endDate): int
    {
        return (int) Leave::where('employee_id', $employee->id)
            ->where('status', LeaveStatusEnum::APPROVED)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            })
            ->sum('duration_days');
    }

    protected function calculateLateClockins(Employee $employee, Carbon $startDate, Carbon $endDate): int
    {
        $lateGraceMinutes = $this->config['thresholds']['late_grace_minutes'] ?? 15;
        $workStartTime = $this->config['defaults']['work_start_time'] ?? '09:00:00';

        $clockings = Clocking::where('subject_type', Employee::class)
            ->where('subject_id', $employee->id)
            ->whereBetween('clocked_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->orderBy('clocked_at')
            ->get();

        $lateCount = 0;
        $processedDays = [];

        foreach ($clockings as $clocking) {
            $dateKey = $clocking->clocked_at->format('Y-m-d');

            if (isset($processedDays[$dateKey])) {
                continue;
            }

            $scheduledStart = Carbon::parse($dateKey . ' ' . $workStartTime);
            $gracePeriod = $scheduledStart->copy()->addMinutes($lateGraceMinutes);

            if ($clocking->clocked_at->gt($gracePeriod)) {
                $lateCount++;
            }

            $processedDays[$dateKey] = true;
        }

        return $lateCount;
    }

    protected function calculateEarlyClockouts(Employee $employee, Carbon $startDate, Carbon $endDate): int
    {
        $earlyDepartureMinutes = $this->config['thresholds']['early_departure_minutes'] ?? 15;
        $workEndTime = $this->config['defaults']['work_end_time'] ?? '17:00:00';

        $clockings = Clocking::where('subject_type', Employee::class)
            ->where('subject_id', $employee->id)
            ->whereBetween('clocked_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->orderByDesc('clocked_at')
            ->get();

        $earlyCount = 0;
        $processedDays = [];

        foreach ($clockings as $clocking) {
            $dateKey = $clocking->clocked_at->format('Y-m-d');

            if (isset($processedDays[$dateKey])) {
                continue;
            }

            $scheduledEnd = Carbon::parse($dateKey . ' ' . $workEndTime);
            $earlyThreshold = $scheduledEnd->copy()->subMinutes($earlyDepartureMinutes);

            if ($clocking->clocked_at->lt($earlyThreshold)) {
                $earlyCount++;
            }

            $processedDays[$dateKey] = true;
        }

        return $earlyCount;
    }

    protected function calculateTotalWorkingHours(Employee $employee, Carbon $startDate, Carbon $endDate): float
    {
        $totalSeconds = Timesheet::where('subject_type', Employee::class)
            ->where('subject_id', $employee->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('working_duration');

        return $totalSeconds / 3600;
    }

    protected function calculateOvertimeHours(float $totalWorkingHours, int $workingDays): float
    {
        $dailyScheduledHours = $this->config['thresholds']['daily_scheduled_hours'] ?? 8.0;
        $overtimeThreshold = $this->config['thresholds']['overtime_threshold_hours'] ?? 8.0;

        $scheduledHours = $workingDays * $dailyScheduledHours;
        $overtime = max(0, $totalWorkingHours - $scheduledHours);

        return $overtime;
    }

    protected function getLeaveBalance(Employee $employee): array
    {
        $currentYear = now()->year;

        $balance = EmployeeLeaveBalance::where('employee_id', $employee->id)
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

    protected function calculateAggregatedLateClockins(int $organisationId, Carbon $startDate, Carbon $endDate): int
    {
        $lateGraceMinutes = $this->config['thresholds']['late_grace_minutes'] ?? 15;
        $workStartTime = $this->config['defaults']['work_start_time'] ?? '08:00:00';

        $clockings = Clocking::query()
            ->join('employees', 'clockings.subject_id', '=', 'employees.id')
            ->where('employees.organisation_id', $organisationId)
            ->where('clockings.subject_type', Employee::class)
            ->whereBetween('clocked_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->select('clockings.subject_id', 'clockings.clocked_at')
            ->orderBy('clockings.subject_id')
            ->orderBy('clockings.clocked_at')
            ->get();

        $lateCount = 0;
        $processedDays = [];

        foreach ($clockings as $clocking) {
            $dateKey = $clocking->subject_id . '_' . $clocking->clocked_at->format('Y-m-d');

            if (isset($processedDays[$dateKey])) {
                continue;
            }

            $dateStr = $clocking->clocked_at->format('Y-m-d');
            $scheduledStart = Carbon::parse($dateStr . ' ' . $workStartTime);
            $gracePeriod = $scheduledStart->copy()->addMinutes($lateGraceMinutes);

            if ($clocking->clocked_at->gt($gracePeriod)) {
                $lateCount++;
            }

            $processedDays[$dateKey] = true;
        }

        return $lateCount;
    }

    protected function calculateAggregatedEarlyClockouts(int $organisationId, Carbon $startDate, Carbon $endDate): int
    {
        $earlyDepartureMinutes = $this->config['thresholds']['early_departure_minutes'] ?? 15;
        $workEndTime = $this->config['defaults']['work_end_time'] ?? '17:00:00';

        $clockings = Clocking::query()
            ->join('employees', 'clockings.subject_id', '=', 'employees.id')
            ->where('employees.organisation_id', $organisationId)
            ->where('clockings.subject_type', Employee::class)
            ->whereBetween('clocked_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->select('clockings.subject_id', 'clockings.clocked_at')
            ->orderBy('clockings.subject_id')
            ->orderByDesc('clockings.clocked_at')
            ->get();

        $earlyCount = 0;
        $processedDays = [];

        foreach ($clockings as $clocking) {
            $dateKey = $clocking->subject_id . '_' . $clocking->clocked_at->format('Y-m-d');

            if (isset($processedDays[$dateKey])) {
                continue;
            }

            $dateStr = $clocking->clocked_at->format('Y-m-d');
            $scheduledEnd = Carbon::parse($dateStr . ' ' . $workEndTime);
            $earlyThreshold = $scheduledEnd->copy()->subMinutes($earlyDepartureMinutes);

            if ($clocking->clocked_at->lt($earlyThreshold)) {
                $earlyCount++;
            }

            $processedDays[$dateKey] = true;
        }

        return $earlyCount;
    }

    protected function calculateAggregatedAttendancePercentage(int $organisationId, Carbon $startDate, Carbon $endDate, int $workingDays): float|null
    {
        if ($workingDays <= 0) {
            return null;
        }

        $employeeIds = DB::table('employees')
            ->where('organisation_id', $organisationId)
            ->where('state', 'working')
            ->pluck('id');

        if ($employeeIds->isEmpty()) {
            return null;
        }

        $presentDaysByEmployee = Clocking::query()
            ->whereIn('subject_id', $employeeIds)
            ->where('subject_type', Employee::class)
            ->whereBetween('clocked_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->select('subject_id', DB::raw('DATE(clocked_at) as clock_date'))
            ->distinct()
            ->get()
            ->groupBy('subject_id')
            ->map(fn ($days) => $days->count());

        $totalPercentage = 0;
        $employeeCount = 0;

        foreach ($employeeIds as $employeeId) {
            $presentDays = $presentDaysByEmployee->get($employeeId, 0);
            $percentage = $workingDays > 0 ? ($presentDays / $workingDays) * 100 : 0;
            $totalPercentage += $percentage;
            $employeeCount++;
        }

        return $employeeCount > 0 ? round($totalPercentage / $employeeCount, 2) : null;
    }

    protected function calculateAggregatedWorkingHours(int $organisationId, Carbon $startDate, Carbon $endDate): float
    {
        $employeeIds = DB::table('employees')
            ->where('organisation_id', $organisationId)
            ->where('state', 'working')
            ->pluck('id');

        $totalSeconds = Timesheet::query()
            ->whereIn('subject_id', $employeeIds)
            ->where('subject_type', Employee::class)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('working_duration');

        return round($totalSeconds / 3600, 2);
    }

    protected function calculateAggregatedOvertimeHours(int $organisationId, Carbon $startDate, Carbon $endDate, int $workingDays, int $totalEmployees): float|null
    {
        if ($totalEmployees <= 0) {
            return null;
        }

        $totalWorkingHours = $this->calculateAggregatedWorkingHours($organisationId, $startDate, $endDate);
        $dailyScheduledHours = $this->config['thresholds']['daily_scheduled_hours'] ?? 8.0;

        $scheduledHours = $workingDays * $dailyScheduledHours * $totalEmployees;
        $totalOvertime = max(0, $totalWorkingHours - $scheduledHours);

        return round($totalOvertime / $totalEmployees, 2);
    }
}
