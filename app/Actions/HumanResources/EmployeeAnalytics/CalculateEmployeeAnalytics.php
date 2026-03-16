<?php

namespace App\Actions\HumanResources\EmployeeAnalytics;

use App\Models\HumanResources\Employee;
use App\Models\HumanResources\EmployeeAnalytics;
use App\Services\EmployeeAnalyticsService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateEmployeeAnalytics implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Employee $employee, Carbon $startDate, Carbon $endDate): string
    {
        return $employee->id.'-'.$startDate->format('Y-m-d').'-'.$endDate->format('Y-m-d');
    }

    public function handle(Employee $employee, Carbon $startDate, Carbon $endDate): EmployeeAnalytics
    {
        $service = new EmployeeAnalyticsService();

        $attendance = $service->calculateAttendanceMetrics($employee, $startDate, $endDate);
        $leave = $service->calculateLeaveMetrics($employee, $startDate, $endDate);
        $summary = $service->calculateSummaryMetrics($attendance, $leave);

        return EmployeeAnalytics::updateOrCreate(
            [
                'employee_id'  => $employee->id,
                'period_start' => $startDate->format('Y-m-d'),
                'period_end'   => $endDate->format('Y-m-d'),
            ],
            [
                'group_id'              => $employee->group_id,
                'organisation_id'       => $employee->organisation_id,
                'working_days'          => $attendance['working_days'],
                'present_days'          => $attendance['present_days'],
                'absent_days'           => $attendance['absent_days'],
                'late_clockins'         => $attendance['late_clockins'],
                'early_clockouts'       => $attendance['early_clockouts'],
                'total_working_hours'   => $attendance['total_working_hours'],
                'overtime_hours'        => $attendance['overtime_hours'],
                'total_leave_days'      => $leave['total_leave_days'],
                'leave_breakdown'       => $leave['leave_breakdown'],
                'attendance_percentage' => $summary['attendance_percentage'],
                'avg_daily_hours'       => $summary['avg_daily_hours'],
                'overtime_ratio'        => $summary['overtime_ratio'],
                'data'                  => [
                    'leave_balance' => $leave['leave_balance'],
                ],
            ]
        );
    }
}
