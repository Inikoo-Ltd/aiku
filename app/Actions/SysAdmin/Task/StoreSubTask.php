<?php

/*
 * Author: Eka Yudinata <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Task;

use App\Actions\SysAdmin\Task\Hydrators\TaskHydrateSubTasks;
use App\Enums\Task\TaskStatusEnum;
use App\Models\SysAdmin\SubTask;
use App\Models\SysAdmin\Task;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreSubTask
{
    use AsAction;

    /**
     * Keyed on the model it points at so a task never accumulates two open sub tasks about the
     * same thing; the partial unique index on sub_tasks enforces the same rule in the database.
     */
    public function handle(Task $task, array $modelData): SubTask
    {
        $subTask = SubTask::updateOrCreate(
            [
                'task_id'    => $task->id,
                'model_type' => $modelData['model_type'],
                'model_id'   => $modelData['model_id'],
                'status'     => TaskStatusEnum::PENDING->value,
            ],
            array_merge($modelData, [
                'group_id'        => $task->group_id,
                'organisation_id' => $task->organisation_id,
            ])
        );

        TaskHydrateSubTasks::dispatch($task);

        return $subTask;
    }
}
