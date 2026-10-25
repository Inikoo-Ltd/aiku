<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Thu, 20 Nov 2025 13:16:33 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Traits;

use App\Helpers\ScheduleLogger;
use Exception;
use App\Models\Helpers\ScheduledTaskLog;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Cache;

trait LoggableSchedule
{
    /**
     * Commands run in the background so a long one never holds back the tasks after it in the same
     * schedule:run, which made them start late and miss their Sentry check-in. The log id travels
     * through the cache under the event's mutex name because the after hook of a background command
     * runs in a separate schedule:finish process.
     */
    protected function logSchedule(Event $event, string $name, string $type, string $scheduledAt): Event
    {
        if (!$event instanceof CallbackEvent) {
            $event->runInBackground();
        }

        $key = 'schedule_log:'.$event->mutexName();

        $event->before(function () use ($key, $name, $type, $scheduledAt) {
            Cache::put($key, ScheduleLogger::start($name, $type, $scheduledAt)->id, now()->addDay());
        });

        $event->after(function () use ($key) {
            try {
                $log = ScheduledTaskLog::find(Cache::pull($key));
                if ($log) {
                    ScheduleLogger::finish($log);
                }
            } catch (Exception $e) {
                logger()->error('Failed to finish schedule log: '.$e->getMessage());
            }
        });

        return $event;
    }

    /**
     * A run killed while the database or Redis is down never releases its overlap lock, so it is kept
     * for Laravel's default 24 hours and every run in between is silently skipped. The default lock
     * is shortened to ten intervals of the task's own schedule; an explicit withoutOverlapping($minutes)
     * is left as written.
     */
    protected function expireOverlapLockWithinRuns(Event $event): void
    {
        $defaultExpiresAt = 1440;

        if (!$event->withoutOverlapping || $event->expiresAt !== $defaultExpiresAt) {
            return;
        }

        $minutesBetweenRuns = (int) $event->nextRunDate()->diffInMinutes($event->nextRunDate(nth: 1));

        $event->expiresAt = min($defaultExpiresAt, max(10, $minutesBetweenRuns * 10));
    }
}
