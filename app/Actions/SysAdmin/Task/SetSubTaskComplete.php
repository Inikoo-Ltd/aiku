<?php

/*
 * Author: Eka Yudinata <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Task;

use App\Actions\Masters\MasterAsset\GetMasterUpdatedBadgeData;
use App\Actions\SysAdmin\Task\Hydrators\TaskHydrateSubTasks;
use App\Enums\Task\TaskStatusEnum;
use App\Models\SysAdmin\SubTask;
use App\Models\SysAdmin\Task;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class SetSubTaskComplete
{
    use AsAction;

    /**
     * Scoped to the tasks the user owns, so a forged id belonging to somebody else's
     * notification task matches nothing and completes nothing.
     *
     * Hydrated synchronously rather than queued: the caller reads the badge count back out of
     * the column in this same response, and would otherwise get the pre-completion number.
     */
    public function handle(User $user, array $subTaskIds): int
    {
        $ownedSubTasks = SubTask::whereIn('id', $subTaskIds)
            ->whereIn('task_id', $user->tasks()->select('tasks.id'))
            ->where('status', TaskStatusEnum::PENDING->value);

        $touchedTaskIds = $ownedSubTasks->clone()->distinct()->pluck('task_id');

        $completed = $ownedSubTasks->update([
            'status'       => TaskStatusEnum::COMPLETED->value,
            'completed_at' => Carbon::now(),
        ]);

        foreach (Task::whereIn('id', $touchedTaskIds)->get() as $task) {
            TaskHydrateSubTasks::run($task);
        }

        return $completed;
    }

    public function rules(): array
    {
        return [
            'sub_task_ids'   => ['required', 'array', 'min:1'],
            'sub_task_ids.*' => ['required', 'integer'],
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $user = $request->user();

        $this->handle($user, $request->validated('sub_task_ids'));

        return response()->json([
            'master_updated_count' => GetMasterUpdatedBadgeData::make()->totalCount($user),
        ]);
    }
}
