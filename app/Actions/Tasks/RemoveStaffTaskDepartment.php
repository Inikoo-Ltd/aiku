<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Mon, 05 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Tasks;

use App\Actions\Chat\Staff\SendStaffMessage;
use App\Events\BroadcastStaffTaskChanged;
use App\Http\Resources\Tasks\StaffTaskResource;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use App\Notifications\StaffTaskNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * A department member decides a task is not their department's (HELP-3571): the department comes off with a reason,
 * the person it is assigned to keeps it as it is, the requester is told why, and the history keeps who, when and why.
 */
class RemoveStaffTaskDepartment
{
    use AsAction;

    public function handle(StaffTask $task, User $actor, string $reason): StaffTask
    {
        $department      = $task->department;
        $departmentLabel = StaffTask::departmentLabel($department);
        $reason          = trim($reason);
        $departmentIds   = StaffTask::departmentMembers($task->requester ?? $actor, $department)->pluck('id')->all();

        StaffTask::withoutAuditing(fn () => $task->update(['department' => null]));

        $task->auditEvent     = 'updated';
        $task->isCustomEvent  = true;
        $task->auditCustomOld = ['department' => $department];
        $task->auditCustomNew = ['department' => null, 'department_note' => $reason];
        Event::dispatch(new AuditCustom($task));
        $task->isCustomEvent = false;

        if ($task->conversation) {
            if (!$task->conversation->hasParticipant($actor)) {
                $task->conversation->addParticipants([$actor->id]);
            }
            SendStaffMessage::run($task->conversation, $actor, ['body' => __('Removed :department from this task', ['department' => $departmentLabel]).': '.$reason]);
        }

        if ($task->requester && $task->requester_id !== $actor->id) {
            Notification::send($task->requester, new StaffTaskNotification(
                $task,
                __(':name removed :department from :reference', ['name' => $actor->chatName(), 'department' => $departmentLabel, 'reference' => $task->reference]),
                $reason
            ));
        }

        BroadcastStaffTaskChanged::dispatch($task);
        SendStaffTaskBadgeUpdateToUsers::run([...$task->involvedUserIds(), ...$departmentIds]);

        return $task;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * Without a person on it, taking the department off would leave the task with nobody at all.
     */
    public function withValidator(Validator $validator, ActionRequest $request): void
    {
        $validator->after(function (Validator $validator) use ($request) {
            if (!$request->route('staffTask')->assignee_id) {
                $validator->errors()->add('reason', __('Give the task to someone before taking the department off, so it is not left with nobody.'));
            }
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('staffTask')->canRemoveDepartmentBy($request->user());
    }

    public function asController(StaffTask $staffTask, ActionRequest $request): StaffTaskResource
    {
        $task = $this->handle($staffTask, $request->user(), $request->validated('reason'));

        return new StaffTaskResource($task->load(['requester', 'assignee', 'collaborators.image', 'conversation.participants', 'model']));
    }
}
