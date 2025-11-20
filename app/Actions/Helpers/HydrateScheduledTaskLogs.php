<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Thu, 20 Nov 2025 14:19:52 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers;

use App\Models\Helpers\ScheduledTaskLog;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class HydrateScheduledTaskLogs
{
    use AsAction;

    public string $commandSignature =
        "hydrate:scheduled-task-logs {--name=hello} {--type=command} {--scheduledAt=00:00}";

    public function asCommand(Command $command): void
    {
        $this->handle(
            $command->option('name'),
            $command->option('type'),
            $command->option('scheduledAt')
        );
    }

    public function handle(string $name, string $type, string $scheduledAt): void
    {
        ScheduledTaskLog::create([
            'task_name'    => $name,
            'task_type'    => $type,
            'scheduled_at' => $scheduledAt,
            'started_at'   => now(),
            'finished_at'  => now(),
            'duration'     => 0,
            'status'       => 'completed',
        ]);
    }
}
