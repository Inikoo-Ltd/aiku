<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Tasks;

use App\Actions\Chat\Staff\SendStaffMessage;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Http\Resources\Tasks\StaffTaskResource;
use App\Models\Chat\StaffConversation;
use App\Models\Chat\StaffMessage;
use App\Models\Tasks\StaffTask;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreStaffTask
{
    use AsAction;

    private ?User $requester = null;

    public function handle(User $requester, array $modelData): StaffTask
    {
        $sourceMessage = isset($modelData['source_message_id']) ? StaffMessage::with('conversation')->find($modelData['source_message_id']) : null;

        return DB::transaction(function () use ($requester, $modelData, $sourceMessage) {
            $number = DB::selectOne('SELECT nextval(?) AS number', ['staff_task_number_seq'])->number;

            $task = StaffTask::create([
                'group_id'     => $requester->group_id,
                'number'       => $number,
                'reference'    => 'TASK-'.$number,
                'subject'      => $modelData['subject'],
                'description'  => $modelData['description'] ?? null,
                'requester_id' => $requester->id,
                'department'   => $modelData['department'] ?? null,
                'assignee_id'  => $modelData['assignee_id'] ?? null,
                'priority'     => $modelData['priority'] ?? ChatPriorityEnum::NORMAL->value,
                'due_at'       => $modelData['due_at'] ?? null,
                'model_type'   => $modelData['model_type'] ?? $sourceMessage?->conversation->context_type,
                'model_id'     => $modelData['model_id'] ?? $sourceMessage?->conversation->context_id,
                'assigned_at'  => isset($modelData['assignee_id']) ? now() : null,
            ]);

            // ponytail: a department task starts with the requester alone in the thread, members see it in their queue and join when they claim it; a department has 10 to 30 supervisors in prod, attaching them all would flood the chat
            $participantIds = collect([$requester->id, $task->assignee_id])->filter()->unique()->values()->all();

            $conversation = StaffConversation::create([
                'group_id'           => $requester->group_id,
                'type'               => 'group',
                'name'               => $task->reference.' · '.Str::limit($task->subject, 60),
                'context_type'       => 'StaffTask',
                'context_id'         => $task->id,
                'created_by_user_id' => $requester->id,
            ]);
            $conversation->participants()->attach($participantIds);
            $task->update(['staff_conversation_id' => $conversation->id]);

            SendStaffMessage::run($conversation, $requester, ['body' => $task->description ?: $task->subject]);

            if ($sourceMessage) {
                SendStaffMessage::run($sourceMessage->conversation, $requester, ['body' => __('Raised :reference: :subject', ['reference' => $task->reference, 'subject' => $task->subject]), 'parent_id' => $sourceMessage->id]);
            }

            if (!empty($modelData['collaborator_ids'])) {
                SyncStaffTaskCollaborators::run($task, $modelData['collaborator_ids'], $requester);
            }

            return $task;
        });
    }

    public function rules(): array
    {
        $groupId = ($this->requester ?? request()->user())->group_id;

        return [
            'subject'           => ['required', 'string', 'max:255'],
            'description'       => ['sometimes', 'nullable', 'string', 'max:5000'],
            'assignee_id'       => ['required_without:department', 'nullable', 'integer', Rule::exists('users', 'id')->where('group_id', $groupId)->where('status', true), fn ($attribute, $value, $fail) => $value && !StaffTask::canBeAssigned(User::find($value)) ? $fail(__('Engineers and QA get tickets, not tasks')) : null],
            'department'        => ['required_without:assignee_id', 'nullable', 'string', Rule::in(array_column(StaffTask::departments($groupId), 'value'))],
            'collaborator_ids'   => ['sometimes', 'array', 'max:20'],
            'collaborator_ids.*' => ['integer', Rule::exists('users', 'id')->where('group_id', $groupId)],
            'priority'          => ['sometimes', Rule::enum(ChatPriorityEnum::class)],
            'due_at'            => ['sometimes', 'nullable', 'date'],
            'model_type'        => ['sometimes', 'nullable', Rule::in(StaffTask::LINKABLE_MODELS)],
            'model_id'          => ['required_with:model_type', 'nullable', 'integer'],
            'source_message_id' => ['sometimes', 'nullable', 'integer', 'exists:staff_messages,id'],
        ];
    }

    public function action(User $requester, array $modelData): StaffTask
    {
        $this->requester = $requester;

        return $this->handle($requester, Validator::make($modelData, $this->rules())->validate());
    }

    public function asController(ActionRequest $request): StaffTaskResource
    {
        $task = $this->handle($request->user(), $request->validated());

        return new StaffTaskResource($task->load(['requester', 'assignee', 'collaborators.image', 'conversation.participants', 'model']));
    }
}
