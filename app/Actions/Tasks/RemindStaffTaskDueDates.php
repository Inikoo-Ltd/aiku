<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Tasks;

use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use App\Notifications\StaffTaskNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Open tasks get one reminder the day before they are due, one on the day and one once they are late. Each stage
 * is sent once per due date, so moving the due date starts the reminders again.
 */
class RemindStaffTaskDueDates
{
    use AsAction;

    public string $commandSignature = 'staff-tasks:remind-due';

    public function handle(): int
    {
        $today    = Carbon::today();
        $tomorrow = $today->copy()->addDay();
        $reminded = 0;

        StaffTask::query()->open()
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $tomorrow->toDateString())
            ->with(['assignee', 'requester', 'collaborators'])
            ->cursor()
            ->each(function (StaffTask $task) use ($today, &$reminded) {
                $dueDate = $task->due_at->toDateString();
                $stage   = match (true) {
                    $task->due_at->lt($today) => 'overdue',
                    $task->due_at->isSameDay($today) => 'today',
                    default => 'tomorrow',
                };

                if (($task->data['due_reminders'][$stage] ?? null) === $dueDate) {
                    return;
                }

                $recipients = $this->recipients($task, $stage);
                if ($recipients->isNotEmpty()) {
                    Notification::send($recipients, new StaffTaskNotification($task, $this->title($task, $stage), $task->subject));
                    SendStaffTaskBadgeUpdateToUsers::run($recipients->pluck('id')->all());
                    $reminded++;
                }

                $data                           = $task->fresh()->data ?? [];
                $data['due_reminders'][$stage] = $dueDate;
                $task->update(['data' => $data]);
            });

        return $reminded;
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(StaffTask $task, string $stage): Collection
    {
        $workers = $task->assignee
            ? collect([$task->assignee])->merge($task->collaborators)
            : ($task->department && $task->requester ? StaffTask::departmentSupervisors($task->requester, $task->department) : collect());

        if ($stage === 'overdue' && $task->requester) {
            $workers->push($task->requester);
        }

        return $workers->filter()->unique('id')->values();
    }

    private function title(StaffTask $task, string $stage): string
    {
        return match ($stage) {
            'overdue' => __(':reference is overdue', ['reference' => $task->reference]),
            'today' => __(':reference is due today', ['reference' => $task->reference]),
            default => __(':reference is due tomorrow', ['reference' => $task->reference]),
        };
    }

    public function asCommand(Command $command): int
    {
        $command->info($this->handle().' due date reminders sent');

        return 0;
    }
}
