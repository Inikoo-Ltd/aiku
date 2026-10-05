<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Tasks;

use App\Actions\Chat\Staff\SendStaffMessage;
use App\Events\BroadcastStaffTaskChanged;
use App\Http\Resources\Tasks\StaffTaskResource;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use App\Notifications\StaffTaskNotification;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Whoever works on a task and is stuck asks for help: the person who raised it and the supervisors of its department
 * (or of the asker's own departments) are told, and the thread keeps the reason.
 */
class RequestStaffTaskHelp
{
    use AsAction;

    public function handle(StaffTask $task, User $asker, ?string $note = null): StaffTask
    {
        $note  = $note ? trim($note) : null;
        $title = __(':name needs help with :reference', ['name' => $asker->chatName(), 'reference' => $task->reference]);

        if ($task->conversation) {
            SendStaffMessage::run($task->conversation, $asker, ['body' => $note ? $title."\n".$note : $title]);
        }

        $departments = $task->department ? [$task->department] : StaffTask::departmentsOf($asker);
        $recipients  = collect([$task->requester])
            ->merge(collect($departments)->flatMap(fn (string $department) => StaffTask::departmentSupervisors($task->requester ?? $asker, $department)))
            ->filter()
            ->reject(fn (User $user) => $user->id === $asker->id)
            ->unique('id')
            ->values();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new StaffTaskNotification($task, $title, $note ?? $task->subject));
        }

        $task->update(['data' => array_merge($task->fresh()->data ?? [], ['help_requested' => ['by_id' => $asker->id, 'at' => now()->toIso8601String()]])]);

        BroadcastStaffTaskChanged::dispatch($task);
        SendStaffTaskBadgeUpdateToUsers::run([...$task->involvedUserIds(), ...$recipients->pluck('id')->all()]);

        return $task;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('staffTask')->canAskForHelpBy($request->user());
    }

    public function rules(): array
    {
        return [
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function asController(StaffTask $staffTask, ActionRequest $request): StaffTaskResource
    {
        $task = $this->handle($staffTask, $request->user(), $request->validated('note'));

        return new StaffTaskResource($task->load(['requester', 'assignee', 'collaborators.image', 'conversation.participants', 'model']));
    }
}
