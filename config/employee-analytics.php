<?php

return [
    'enabled' =>
        env('EMPLOYEE_ANALYTICS_ENABLED', false),

    'thresholds' => [
        'late_grace_minutes'       => (int) env('ANALYTICS_LATE_GRACE_MINUTES', 15),
        'early_departure_minutes'  => (int) env('ANALYTICS_EARLY_DEPARTURE_MINUTES', 15),
        'daily_scheduled_hours'    => (float) env('ANALYTICS_DAILY_SCHEDULED_HOURS', 8.0),
        'overtime_threshold_hours' => (float) env('ANALYTICS_OVERTIME_THRESHOLD_HOURS', 8.0),
    ],

    'defaults' => [
        'work_start_time' => env('ANALYTICS_WORK_START_TIME', '08:00:00'),
        'work_end_time'   => env('ANALYTICS_WORK_END_TIME', '17:00:00'),
    ],

    'cache_ttl' => (int) env('EMPLOYEE_ANALYTICS_CACHE_TTL', 3600),
];
