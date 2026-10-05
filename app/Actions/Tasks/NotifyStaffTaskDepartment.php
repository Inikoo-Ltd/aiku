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
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A task sent to a department rather than a person is told to everyone in that department, so somebody picks it up.
 */
class NotifyStaffTaskDepartment
{
    use AsAction;

    public function handle(StaffTask $task, User $actor): int
    {
        if (!$task->department || $task->assignee_id) {
            return 0;
        }

        $members = StaffTask::departmentMembers($task->requester ?? $actor, $task->department)
            ->reject(fn (User $member) => in_array($member->id, [$actor->id, $task->requester_id], true))
            ->filter(fn (User $member) => StaffTask::canBeAssigned($member))
            ->values();

        if ($members->isEmpty()) {
            return 0;
        }

        Notification::send($members, new StaffTaskNotification(
            $task,
            __(':reference is waiting for someone from :department', ['reference' => $task->reference, 'department' => StaffTask::departmentLabel($task->department)]),
            $task->subject
        ));
        SendStaffTaskBadgeUpdateToUsers::run($members->pluck('id')->all());

        return $members->count();
    }
}
