<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\StaffTask;

use App\Models\Chat\StaffTask;
use App\Notifications\StaffTaskNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Open tasks whose thread has been quiet for 48 hours get one nudge, then another every 48 hours:
 * the assignee if there is one, otherwise the supervisors of the department the task was sent to.
 * No SLA configuration by design, one clock for everything.
 */
class NudgeStaleStaffTasks
{
    use AsAction;

    public string $commandSignature = 'staff-tasks:nudge {--hours=48 : hours of silence before a nudge}';

    public function handle(int $hours = 48): int
    {
        $cutoff = now()->subHours($hours);
        $nudged = 0;

        StaffTask::query()->open()
            ->whereHas('conversation', fn ($query) => $query->where('last_message_at', '<', $cutoff))
            ->where(fn ($query) => $query->whereNull('data->nudged_at')->orWhere('data->nudged_at', '<', $cutoff->toIso8601String()))
            ->with(['assignee', 'requester'])
            ->cursor()
            ->each(function (StaffTask $task) use ($hours, &$nudged) {
                $recipients = $task->assignee
                    ? collect([$task->assignee])
                    : ($task->department ? StaffTask::departmentSupervisors($task->requester, $task->department) : collect());

                if ($recipients->isEmpty()) {
                    return;
                }

                $title = $task->assignee
                    ? __(':reference is still open', ['reference' => $task->reference])
                    : __('Nobody has picked up :reference', ['reference' => $task->reference]);

                Notification::send($recipients, new StaffTaskNotification($task, $title, __(':subject, quiet for :hours hours', ['subject' => $task->subject, 'hours' => $hours])));
                $task->update(['data' => array_merge($task->data, ['nudged_at' => now()->toIso8601String()])]);
                $nudged++;
            });

        return $nudged;
    }

    public function asCommand(Command $command): int
    {
        $command->info($this->handle((int) $command->option('hours')).' tasks nudged');

        return 0;
    }
}
