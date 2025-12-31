<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

use App\Actions\Helpers\TimeSeries\ResetDailyTimeSeries;
use App\Actions\Helpers\TimeSeries\ResetMonthlyTimeSeries;
use App\Actions\Helpers\TimeSeries\ResetQuarterlyTimeSeries;
use App\Actions\Helpers\TimeSeries\ResetWeeklyTimeSeries;
use App\Actions\Helpers\TimeSeries\ResetYearlyTimeSeries;
use Carbon\Carbon;

it('daily time series uses yesterday date range in UTC', function () {
    Carbon::setTestNow(Carbon::parse('2025-12-24 02:00:00', 'UTC'));

    $action = new ResetDailyTimeSeries();
    $reflection = new ReflectionClass($action);
    $method = $reflection->getMethod('getDateRangeForFrequency');
    $method->setAccessible(true);

    $dateRange = $method->invoke($action);

    expect($dateRange['from']->toDateTimeString())->toBe('2025-12-23 00:00:00');
    expect($dateRange['to']->toDateTimeString())->toBe('2025-12-23 23:59:59');
    expect($dateRange['from']->timezone->getName())->toBe('UTC');
    expect($dateRange['to']->timezone->getName())->toBe('UTC');
});

it('weekly time series uses last week date range in UTC', function () {
    Carbon::setTestNow(Carbon::parse('2025-12-24 02:00:00', 'UTC'));

    $action = new ResetWeeklyTimeSeries();
    $reflection = new ReflectionClass($action);
    $method = $reflection->getMethod('getDateRangeForFrequency');
    $method->setAccessible(true);

    $dateRange = $method->invoke($action);

    $expectedFrom = Carbon::parse('2025-12-16', 'UTC')->startOfWeek();
    $expectedTo = Carbon::parse('2025-12-16', 'UTC')->endOfWeek();

    expect($dateRange['from']->equalTo($expectedFrom))->toBeTrue();
    expect($dateRange['to']->equalTo($expectedTo))->toBeTrue();
    expect($dateRange['from']->timezone->getName())->toBe('UTC');
});

it('monthly time series uses last month date range in UTC', function () {
    Carbon::setTestNow(Carbon::parse('2025-12-24 02:00:00', 'UTC'));

    $action = new ResetMonthlyTimeSeries();
    $reflection = new ReflectionClass($action);
    $method = $reflection->getMethod('getDateRangeForFrequency');
    $method->setAccessible(true);

    $dateRange = $method->invoke($action);

    expect($dateRange['from']->toDateTimeString())->toBe('2025-11-01 00:00:00');
    expect($dateRange['to']->toDateTimeString())->toBe('2025-11-30 23:59:59');
    expect($dateRange['from']->timezone->getName())->toBe('UTC');
});

it('quarterly time series uses last quarter date range in UTC', function () {
    Carbon::setTestNow(Carbon::parse('2025-12-24 02:00:00', 'UTC'));

    $action = new ResetQuarterlyTimeSeries();
    $reflection = new ReflectionClass($action);
    $method = $reflection->getMethod('getDateRangeForFrequency');
    $method->setAccessible(true);

    $dateRange = $method->invoke($action);

    expect($dateRange['from']->toDateTimeString())->toBe('2025-07-01 00:00:00');
    expect($dateRange['to']->toDateTimeString())->toBe('2025-09-30 23:59:59');
    expect($dateRange['from']->timezone->getName())->toBe('UTC');
});

it('yearly time series uses last year date range in UTC', function () {
    Carbon::setTestNow(Carbon::parse('2025-12-24 02:00:00', 'UTC'));

    $action = new ResetYearlyTimeSeries();
    $reflection = new ReflectionClass($action);
    $method = $reflection->getMethod('getDateRangeForFrequency');
    $method->setAccessible(true);

    $dateRange = $method->invoke($action);

    expect($dateRange['from']->toDateTimeString())->toBe('2024-01-01 00:00:00');
    expect($dateRange['to']->toDateTimeString())->toBe('2024-12-31 23:59:59');
    expect($dateRange['from']->timezone->getName())->toBe('UTC');
});

it('daily reset prevents data gaps by always using completed day', function () {
    Carbon::setTestNow(Carbon::parse('2025-12-24 23:59:59', 'Asia/Singapore'));

    $action = new ResetDailyTimeSeries();
    $reflection = new ReflectionClass($action);
    $method = $reflection->getMethod('getDateRangeForFrequency');
    $method->setAccessible(true);

    $dateRange = $method->invoke($action);

    expect($dateRange['from']->toDateTimeString())->toBe('2025-12-23 00:00:00');
    expect($dateRange['to']->toDateTimeString())->toBe('2025-12-23 23:59:59');
    expect($dateRange['from']->timezone->getName())->toBe('UTC');
});
