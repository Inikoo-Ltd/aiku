<?php

use App\Services\EmployeeAnalyticsService;
use Carbon\Carbon;

beforeEach(function () {
    $this->service = new EmployeeAnalyticsService();
});

describe('calculateSummaryMetrics', function () {
    it('calculates attendance percentage correctly', function () {
        $attendance = [
            'working_days'        => 20,
            'present_days'        => 18,
            'total_working_hours' => 144,
            'overtime_hours'      => 0,
        ];
        $leave = ['total_leave_days' => 2];

        $result = $this->service->calculateSummaryMetrics($attendance, $leave);

        expect($result['attendance_percentage'])->toBe(90.0);
    });

    it('calculates average daily hours correctly', function () {
        $attendance = [
            'working_days'        => 20,
            'present_days'        => 18,
            'total_working_hours' => 144,
            'overtime_hours'      => 0,
        ];
        $leave = ['total_leave_days' => 2];

        $result = $this->service->calculateSummaryMetrics($attendance, $leave);

        expect($result['avg_daily_hours'])->toBe(8.0);
    });

    it('calculates overtime ratio correctly', function () {
        $attendance = [
            'working_days'        => 20,
            'present_days'        => 18,
            'total_working_hours' => 160,
            'overtime_hours'      => 16,
        ];
        $leave = ['total_leave_days' => 2];

        $result = $this->service->calculateSummaryMetrics($attendance, $leave);

        expect($result['overtime_ratio'])->toBe(10.0);
    });

    it('handles division by zero gracefully', function () {
        $attendance = [
            'working_days'        => 0,
            'present_days'        => 0,
            'total_working_hours' => 0,
            'overtime_hours'      => 0,
        ];
        $leave = ['total_leave_days' => 0];

        $result = $this->service->calculateSummaryMetrics($attendance, $leave);

        expect($result)
            ->attendance_percentage->toBe(0)
            ->avg_daily_hours->toBe(0)
            ->overtime_ratio->toBe(0);
    });
});

describe('calculateWorkingDays', function () {
    it('calculates working days excluding weekends', function () {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateWorkingDays');
        $method->setAccessible(true);

        $startDate = Carbon::parse('2026-02-16');
        $endDate = Carbon::parse('2026-02-22');

        $result = $method->invoke($this->service, $startDate, $endDate);

        expect($result)->toBe(5);
    });

    it('returns correct count for single week', function () {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateWorkingDays');
        $method->setAccessible(true);

        $startDate = Carbon::parse('2026-02-16');
        $endDate = Carbon::parse('2026-02-20');

        $result = $method->invoke($this->service, $startDate, $endDate);

        expect($result)->toBe(5);
    });

    it('returns 0 for weekend-only range', function () {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateWorkingDays');
        $method->setAccessible(true);

        $startDate = Carbon::parse('2026-02-21');
        $endDate = Carbon::parse('2026-02-22');

        $result = $method->invoke($this->service, $startDate, $endDate);

        expect($result)->toBe(0);
    });
});

describe('calculateOvertimeHours', function () {
    it('returns zero when under threshold', function () {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateOvertimeHours');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 30.0, 5);

        expect($result)->toBe(0.0);
    });

    it('returns positive value when over threshold', function () {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateOvertimeHours');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 50.0, 5);

        expect($result)->toBe(10.0);
    });

    it('handles zero working days', function () {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateOvertimeHours');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 0.0, 0);

        expect($result)->toBe(0.0);
    });
});

describe('feature flag', function () {
    it('feature flag is disabled by default', function () {
        config(['employee-analytics.enabled' => false]);

        expect(config('employee-analytics.enabled'))->toBeFalse();
    });

    it('feature flag can be enabled via env', function () {
        config(['employee-analytics.enabled' => true]);

        expect(config('employee-analytics.enabled'))->toBeTrue();
    });
});

describe('configuration thresholds', function () {
    it('default late grace minutes is 15', function () {
        expect(config('employee-analytics.thresholds.late_grace_minutes'))->toBe(15);
    });

    it('default daily scheduled hours is 8', function () {
        expect(config('employee-analytics.thresholds.daily_scheduled_hours'))->toBe(8.0);
    });

    it('default work start time is 08:00', function () {
        expect(config('employee-analytics.defaults.work_start_time'))->toBe('08:00:00');
    });

    it('default work end time is 17:00', function () {
        expect(config('employee-analytics.defaults.work_end_time'))->toBe('17:00:00');
    });
});

describe('overtime calculation with config', function () {
    it('uses configured daily scheduled hours for overtime', function () {
        config(['employee-analytics.thresholds.daily_scheduled_hours' => 7.5]);

        $service = new EmployeeAnalyticsService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('calculateOvertimeHours');
        $method->setAccessible(true);

        $result = $method->invoke($service, 50.0, 5);

        expect($result)->toBe(12.5);
    });
});
