<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Thu, 20 Nov 2025 11:12:00 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Helpers;

use App\Models\Helpers\ScheduledTaskLog;
use Exception;
use Illuminate\Support\Facades\Log;

class ScheduleLogger
{
    /**
     * @throws Exception
     */
    public static function start(string $taskName, string $taskType, string $scheduledAt): ScheduledTaskLog
    {
        try {
            return ScheduledTaskLog::create([
                'task_name'    => $taskName,
                'task_type'    => $taskType,
                'scheduled_at' => $scheduledAt,
                'started_at'   => now(),
                'status'       => 'running',
            ]);
        } catch (Exception $e) {
            Log::error('Failed to start schedule log: ' . $e->getMessage());
            throw $e;
        }
    }

    public static function finish(ScheduledTaskLog $log): void
    {
        try {
            $log->update([
                'finished_at' => now(),
                'status'      => 'completed',
                'duration'    => $log->started_at->diffInSeconds(now()),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to finish schedule log: ' . $e->getMessage());
        }
    }

    public static function error(ScheduledTaskLog $log, string $errorMessage): void
    {
        try {
            $log->update([
                'finished_at'   => now(),
                'status'        => 'failed',
                'error_message' => $errorMessage,
                'duration'      => $log->started_at->diffInSeconds(now()),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update error schedule log: ' . $e->getMessage());
        }
    }
}
