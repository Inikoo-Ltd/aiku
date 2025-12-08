<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:24:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Task;

use App\Enums\Task\TaskStatusEnum;
use App\Models\SysAdmin\Task;
use App\Models\SysAdmin\User;
use Carbon\Carbon;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SetTaskComplete
{
    use AsAction;
    use WithAttributes;

    public function handle(Task $task, User $user): bool
    {
        $pivot = $user->tasks()->wherePivot('task_id', $task->id)->first();

        if ($pivot) {
            $pivot->update([
                'status' => TaskStatusEnum::COMPLETED->value,
                'complete_date' => Carbon::now(),
            ]);

            return true;
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'task_id' => ['required', 'exists:tasks,id'],
            'user_id' => ['required', 'exists:users,id'],
        ];
    }

    public function asController(Task $task, User $user, ActionRequest $request): bool
    {
        return $this->handle($task, $user);
    }
}
