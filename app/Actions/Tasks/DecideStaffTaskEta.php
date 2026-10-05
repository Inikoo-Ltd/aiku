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
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class DecideStaffTaskEta
{
    use AsAction;

    public const array DECISIONS = ['accept', 'decline'];

    public function handle(StaffTask $task, User $decider, string $decision): StaffTask
    {
        $proposal = DB::transaction(function () use ($task) {
            $lockedTask = StaffTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $proposal   = $lockedTask->data['eta_proposal'] ?? null;

            if (!$proposal) {
                throw ValidationException::withMessages(['decision' => __('There is no new ETA waiting on :reference', ['reference' => $lockedTask->reference])]);
            }

            $lockedTask->update(['data' => Arr::except($lockedTask->data, 'eta_proposal')]);

            return $proposal;
        });

        $task->refresh();
        $date = Carbon::parse($proposal['due_at'])->isoFormat('D MMM');

        if ($decision === 'accept') {
            $task  = UpdateStaffTask::run($task, $decider, ['due_at' => $proposal['due_at']]);
            $title = __('New ETA for :reference accepted: :date', ['reference' => $task->reference, 'date' => $date]);
        } else {
            BroadcastStaffTaskChanged::dispatch($task);
            SendStaffTaskBadgeUpdateToUsers::run($task->involvedUserIds());
            $title = __('New ETA for :reference declined, keep :date', ['reference' => $task->reference, 'date' => $task->due_at?->isoFormat('D MMM') ?? __('no due date')]);
        }

        if ($task->conversation) {
            SendStaffMessage::run($task->conversation, $decider, ['body' => $title]);
        }

        $proposer = User::find($proposal['by_id']);
        if ($proposer && $proposer->id !== $decider->id) {
            Notification::send($proposer, new StaffTaskNotification($task, $title, $task->subject));
        }

        return $task;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('staffTask')->canSetDueDate($request->user());
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(self::DECISIONS)],
        ];
    }

    public function asController(StaffTask $staffTask, ActionRequest $request): StaffTaskResource
    {
        $task = $this->handle($staffTask, $request->user(), $request->validated('decision'));

        return new StaffTaskResource($task->load(['requester', 'assignee', 'collaborators.image', 'conversation.participants', 'model']));
    }
}
