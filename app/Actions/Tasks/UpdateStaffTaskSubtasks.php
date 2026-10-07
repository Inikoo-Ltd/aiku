<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Tasks;

use App\Events\BroadcastStaffTaskChanged;
use App\Http\Resources\Tasks\StaffTaskResource;
use App\Models\Tasks\StaffTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateStaffTaskSubtasks
{
    use AsAction;

    /**
     * The whole list is replaced, under a row lock so a nudge writing to data at the same time is not lost.
     *
     * @param array<int, array{title: string, status: string}> $subtasks
     */
    public function handle(StaffTask $task, array $subtasks): StaffTask
    {
        return DB::transaction(function () use ($task, $subtasks) {
            $lockedTask = StaffTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            $lockedTask->update([
                'data' => [
                    ...($lockedTask->data ?? []),
                    'subtasks' => collect($subtasks)->map(fn (array $subtask) => [
                        'title'  => trim($subtask['title']),
                        'status' => $subtask['status'],
                    ])->values()->all(),
                ],
            ]);

            BroadcastStaffTaskChanged::dispatch($lockedTask);

            return $lockedTask;
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('staffTask')->isWorkedOnBy($request->user());
    }

    public function rules(): array
    {
        return [
            'subtasks'          => ['present', 'array'],
            'subtasks.*.title'  => ['required', 'string', 'max:255'],
            'subtasks.*.status' => ['required', Rule::in(StaffTask::SUBTASK_STATUSES)],
        ];
    }

    public function asController(StaffTask $staffTask, ActionRequest $request): StaffTaskResource
    {
        $task = $this->handle($staffTask, $request->validated('subtasks'));

        return new StaffTaskResource($task->load(['requester', 'assignee', 'collaborators.image', 'conversation.participants', 'model']));
    }
}
