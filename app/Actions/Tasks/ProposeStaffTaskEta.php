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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ProposeStaffTaskEta
{
    use AsAction;

    /**
     * @param array{due_at: string, reason: string} $modelData
     */
    public function handle(StaffTask $task, User $proposer, array $modelData): StaffTask
    {
        $dueAt = Carbon::parse($modelData['due_at'])->toDateString();

        $task = DB::transaction(function () use ($task, $proposer, $modelData, $dueAt) {
            $lockedTask = StaffTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            $lockedTask->update([
                'data' => [
                    ...($lockedTask->data ?? []),
                    'eta_proposal' => [
                        'due_at'          => $dueAt,
                        'previous_due_at' => $lockedTask->due_at?->toDateString(),
                        'reason'          => trim($modelData['reason']),
                        'by_id'           => $proposer->id,
                        'by_name'         => $proposer->chatName(),
                        'at'              => now()->toIso8601String(),
                    ],
                ],
            ]);

            return $lockedTask;
        });

        $title = __(':name suggests a new ETA for :reference: :date', ['name' => $proposer->chatName(), 'reference' => $task->reference, 'date' => Carbon::parse($dueAt)->isoFormat('D MMM')]);

        if ($task->conversation) {
            SendStaffMessage::run($task->conversation, $proposer, ['body' => $title."\n".trim($modelData['reason'])]);
        }

        if ($task->requester_id !== $proposer->id) {
            Notification::send($task->requester, new StaffTaskNotification($task, $title, trim($modelData['reason'])));
        }

        BroadcastStaffTaskChanged::dispatch($task);
        SendStaffTaskBadgeUpdateToUsers::run($task->involvedUserIds());

        return $task;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('staffTask')->canSuggestEta($request->user());
    }

    public function rules(): array
    {
        return [
            'due_at' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function asController(StaffTask $staffTask, ActionRequest $request): StaffTaskResource
    {
        $task = $this->handle($staffTask, $request->user(), $request->validated());

        return new StaffTaskResource($task->load(['requester', 'assignee', 'collaborators.image', 'conversation.participants', 'model']));
    }
}
