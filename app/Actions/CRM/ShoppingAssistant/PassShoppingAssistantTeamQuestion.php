<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Chat\Staff\SendStaffMessage;
use App\Actions\Tasks\GetAikuAssistant;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * "Pass to a colleague" on a question pop-up: the colleague who knows becomes the task's assignee,
 * so they may answer, and gets the pop-up at once. Said on the task.
 */
class PassShoppingAssistantTeamQuestion
{
    use AsAction;

    public function handle(StaffTask $task, User $from, User $to): void
    {
        if (data_get($task->data, 'answered_at') || $to->id === $task->assignee_id) {
            return;
        }

        $task->update(['assignee_id' => $to->id, 'assigned_at' => now(), 'data' => [...$task->data, 'passed' => [...data_get($task->data, 'passed', []), ['from' => $from->id, 'to' => $to->id, 'at' => now()->toIso8601String()]]]]);

        if ($task->conversation) {
            if (!$task->conversation->hasParticipant($to)) {
                $task->conversation->addParticipants([$to->id]);
            }
            SendStaffMessage::run($task->conversation, GetAikuAssistant::run($task->group_id), ['body' => __(':from passed this question to :to.', ['from' => $from->contact_name ?: $from->username, 'to' => $to->contact_name ?: $to->username])]);
        }

        AlertShoppingAssistantTeamQuestion::dispatch($task, $to);
    }

    public function authorize(ActionRequest $request): bool
    {
        return StoreShoppingAssistantTeamQuestion::canAnswer($request->route('staffTask'), $request->user());
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('group_id', request()->user()?->group_id)->where('status', true)],
        ];
    }

    public function asController(StaffTask $staffTask, ActionRequest $request): JsonResponse
    {
        $this->handle($staffTask, $request->user(), User::find($request->validated('user_id')));

        return response()->json(['result' => 'passed']);
    }
}
