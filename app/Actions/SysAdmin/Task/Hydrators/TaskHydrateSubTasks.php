<?php

/*
 * Author: Eka Yudinata <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Task\Hydrators;

use App\Enums\Task\TaskStatusEnum;
use App\Events\BroadcastMasterUpdatedCountUpdate;
use App\Models\SysAdmin\Task;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class TaskHydrateSubTasks implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Task $task): string
    {
        return $task->id;
    }

    /**
     * The column is written quietly, so no model event carries the new count to the badge; the
     * broadcast is what keeps an already open page in step with a sub task stored in the background.
     */
    public function handle(Task $task): void
    {
        $task->updateQuietly([
            'number_subtasks' => $task->subTasks()->where('status', TaskStatusEnum::PENDING->value)->count(),
        ]);

        foreach ($task->users as $user) {
            BroadcastMasterUpdatedCountUpdate::dispatch($user->id, $task->number_subtasks);
        }
    }
}
