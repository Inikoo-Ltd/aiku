<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Tasks;

use App\Actions\Chat\Staff\SendStaffMessage;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Events\BroadcastStaffTaskChanged;
use App\Http\Resources\Tasks\StaffTaskResource;
use App\Models\Tasks\StaffTask;
use App\Models\SysAdmin\User;
use App\Notifications\StaffTaskNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateStaffTask
{
    use AsAction;

    public function handle(StaffTask $task, User $actor, array $modelData): StaffTask
    {
        $note               = Arr::pull($modelData, 'note');
        $lines              = [];
        $previousAssigneeId = $task->assignee_id;

        if (array_key_exists('assignee_id', $modelData) && $modelData['assignee_id'] !== $task->assignee_id) {
            $modelData['assigned_at'] = $modelData['assignee_id'] ? now() : null;
            if ($modelData['assignee_id']) {
                $assignee = User::find($modelData['assignee_id']);
                $task->conversation?->addParticipants([$assignee->id]);
                $lines[] = $assignee->id === $actor->id ? __('I will take this one') : __('Assigned to :name', ['name' => $assignee->chatName()]);
            }
        }

        if (isset($modelData['status']) && $modelData['status'] !== $task->status->value) {
            $status = StaffTaskStatusEnum::from($modelData['status']);
            $lines[] = StaffTaskStatusEnum::labels()[$status->value];
            if ($status === StaffTaskStatusEnum::IN_PROGRESS) {
                $modelData['started_at'] ??= now();
                if (!$task->assignee_id && !isset($modelData['assignee_id'])) {
                    $modelData['assignee_id'] = $actor->id;
                    $modelData['assigned_at'] = now();
                }
            }
            $modelData['closed_at'] = $status->isOpen() ? null : now();
        }

        $task->update($modelData);

        if ($task->wasChanged('assignee_id') && $task->assignee_id) {
            $task->collaborators()->detach($task->assignee_id);
        }

        if ($task->wasChanged('assignee_id') && $previousAssigneeId && $task->conversation) {
            $stillInvolved = $previousAssigneeId === $task->requester_id
                || $task->collaborators()->where('users.id', $previousAssigneeId)->exists();

            if (!$stillInvolved) {
                $task->conversation->removeParticipants([$previousAssigneeId]);
            }
        }

        if ($note) {
            $lines[] = $note;
        }
        if ($lines && $task->conversation) {
            if (!$task->conversation->hasParticipant($actor)) {
                $task->conversation->addParticipants([$actor->id]);
            }
            SendStaffMessage::run($task->conversation, $actor, ['body' => implode(': ', $lines)]);
        }

        $this->notify($task, $actor);

        BroadcastStaffTaskChanged::dispatch($task);
        SendStaffTaskBadgeUpdateToUsers::run([...$task->involvedUserIds(), $previousAssigneeId]);

        return $task;
    }

    private function notify(StaffTask $task, User $actor): void
    {
        if ($task->wasChanged('assignee_id') && $task->assignee_id && $task->assignee_id !== $actor->id) {
            Notification::send($task->assignee, new StaffTaskNotification($task, __(':reference is for you', ['reference' => $task->reference]), $task->subject));
        }

        if ($task->wasChanged('department')) {
            NotifyStaffTaskDepartment::run($task, $actor);
        }

        if ($task->wasChanged('status') && !$task->status->isOpen() && $task->requester_id !== $actor->id) {
            $title = $task->status === StaffTaskStatusEnum::DONE
                ? __(':reference is done', ['reference' => $task->reference])
                : __(':reference can\'t be done', ['reference' => $task->reference]);

            Notification::send($task->requester, new StaffTaskNotification($task, $title, $task->subject));
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        $task = $request->route('staffTask');

        if ($request->has('due_at') && !$task->canSetDueDate($request->user())) {
            return false;
        }

        if ($request->has('assignee_id') && (int) $request->input('assignee_id') !== (int) $task->assignee_id) {
            $isClaimingUnassigned = !$task->assignee_id && (int) $request->input('assignee_id') === $request->user()->id;
            if (!$isClaimingUnassigned && !$task->canReassignBy($request->user())) {
                return false;
            }
        }

        return $task->isVisibleTo($request->user());
    }

    public function rules(): array
    {
        $groupId = request()->user()->group_id;

        return [
            'subject'     => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status'      => ['sometimes', Rule::enum(StaffTaskStatusEnum::class)],
            'note'        => ['required_if:status,cancelled', 'nullable', 'string', 'max:1000'],
            'assignee_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where('group_id', $groupId)->where('status', true), fn ($attribute, $value, $fail) => $value && !StaffTask::canBeAssigned(User::find($value)) ? $fail(__('Engineers and QA get tickets, not tasks')) : null],
            'department'  => ['sometimes', 'nullable', 'string', Rule::in(array_column(StaffTask::departments($groupId), 'value'))],
            'priority'    => ['sometimes', Rule::enum(ChatPriorityEnum::class)],
            'due_at'      => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function asController(StaffTask $staffTask, ActionRequest $request): StaffTaskResource
    {
        $task = $this->handle($staffTask, $request->user(), $request->validated());

        return new StaffTaskResource($task->load(['requester', 'assignee', 'collaborators.image', 'conversation.participants', 'model']));
    }
}
