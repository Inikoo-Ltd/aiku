<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Thu, 20 Nov 2025 13:16:33 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Traits;

use App\Helpers\ScheduleLogger;
use Exception;
use Illuminate\Console\Scheduling\Event;

trait LoggableSchedule
{
    /**
     * Wrap schedule event with logging
     */
    protected function logSchedule(Event $event, string $name, string $type, string $scheduledAt): Event
    {
        // Generate unique key for this schedule instance
        $key = 'schedule_log_'.md5($name.$scheduledAt.now()->timestamp);

        $event->before(function () use ($key, $name, $type, $scheduledAt) {
            // Store the log instance in service container
            app()->instance($key, ScheduleLogger::start($name, $type, $scheduledAt));
        });

        $event->after(function () use ($key) {
            try {
                if (app()->bound($key)) {
                    $log = app($key);
                    ScheduleLogger::finish($log);
                }
            } catch (Exception $e) {
                // Log error but don't break the schedule
                logger()->error('Failed to finish schedule log: '.$e->getMessage());
            }
        });

        return $event;
    }
}
