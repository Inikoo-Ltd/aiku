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
use App\Http\Resources\Tasks\StaffTaskResource;
use App\Models\Tasks\StaffTask;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateStaffTask
{
    use AsAction;

    public function handle(StaffTask $task, User $actor, array $modelData): StaffTask
    {
        $note = Arr::pull($modelData, 'note');
        $lines = [];

        if (array_key_exists('assignee_id', $modelData) && $modelData['assignee_id'] !== $task->assignee_id) {
            $modelData['assigned_at'] = $modelData['assignee_id'] ? now() : null;
            if ($modelData['assignee_id']) {
                $assignee = User::find($modelData['assignee_id']);
                $task->conversation?->participants()->syncWithoutDetaching([$assignee->id]);
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

        if ($note) {
            $lines[] = $note;
        }
        if ($lines && $task->conversation) {
            $task->conversation->participants()->syncWithoutDetaching([$actor->id]);
            SendStaffMessage::run($task->conversation, $actor, ['body' => implode(': ', $lines)]);
        }

        return $task;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('staffTask')->group_id === $request->user()->group_id;
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

        return new StaffTaskResource($task->load(['requester', 'assignee', 'conversation', 'model']));
    }
}
