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
        return DB::table('employee_analytics')
            ->where('organisation_id', $organisationId)
            ->where('period_start', '>=', $startDate)
            ->where('period_end', '<=', $endDate)
            ->selectRaw('
                COUNT(DISTINCT employee_id) as total_employees,
                AVG(attendance_percentage) as avg_attendance_percentage,
                AVG(total_working_hours) as avg_total_working_hours,
                AVG(overtime_hours) as avg_overtime_hours,
                SUM(late_clockins) as total_late_clockins,
                SUM(early_clockouts) as total_early_clockouts,
                SUM(total_leave_days) as total_leave_days
            ')
            ->first();
    }

    public function getEmployeeAttendanceBreakdown(int $organisationId, Carbon $startDate, Carbon $endDate, int $limit = 10): array
    {
        $results = DB::table('employee_analytics')
            ->join('employees', 'employee_analytics.employee_id', '=', 'employees.id')
            ->where('employee_analytics.organisation_id', $organisationId)
            ->where('employee_analytics.period_start', '>=', $startDate)
            ->where('employee_analytics.period_end', '<=', $endDate)
            ->select([
                'employees.id',
                'employees.contact_name',
                'employees.slug',
                'employee_analytics.attendance_percentage',
                'employee_analytics.late_clockins',
                'employee_analytics.early_clockouts',
            ])
            ->orderByDesc('employee_analytics.attendance_percentage')
            ->limit($limit)
            ->get();

        return $results->map(fn ($row) => [
            'id' => $row->id,
            'name' => $row->contact_name,
            'slug' => $row->slug,
            'attendance_percentage' => round($row->attendance_percentage ?? 0, 2),
            'late_clockins' => $row->late_clockins ?? 0,
            'early_clockouts' => $row->early_clockouts ?? 0,
        ])->toArray();
    }

    public function getTopEmployeesByLeave(int $organisationId, Carbon $startDate, Carbon $endDate, int $limit = 10): array
    {
        $results = DB::table('employee_analytics')
            ->join('employees', 'employee_analytics.employee_id', '=', 'employees.id')
            ->where('employee_analytics.organisation_id', $organisationId)
            ->where('employee_analytics.period_start', '>=', $startDate)
            ->where('employee_analytics.period_end', '<=', $endDate)
            ->where('employee_analytics.total_leave_days', '>', 0)
            ->select([
                'employees.id',
                'employees.contact_name',
                'employees.slug',
                'employee_analytics.total_leave_days',
                'employee_analytics.leave_breakdown',
            ])
            ->orderByDesc('employee_analytics.total_leave_days')
            ->limit($limit)
            ->get();

        return $results->map(fn ($row) => [
            'id' => $row->id,
            'name' => $row->contact_name,
            'slug' => $row->slug,
            'total_leave_days' => $row->total_leave_days ?? 0,
            'leave_breakdown' => json_decode($row->leave_breakdown ?? '{}', true),
        ])->toArray();
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
}
