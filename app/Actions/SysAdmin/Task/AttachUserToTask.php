<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:24:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Task;

use App\Actions\OrgAction;
use App\Enums\Task\TaskStatusEnum;
use App\Models\SysAdmin\Task;
use App\Models\SysAdmin\User;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class AttachUserToTask extends OrgAction
{
    public function handle(Task $task, $user, array $pivotData): void
    {
        $task->users()->attach($user->id, $pivotData);
    }

    public function rules(): array
    {
        return [
            'start_date'    => ['sometimes', 'date'],
            'complete_date' => ['sometimes', 'date'],
            'deadline'      => ['sometimes', 'date'],
            'status'        => ['sometimes', 'string', Rule::enum(TaskStatusEnum::class)],
        ];
    }

    public function asController(Task $task, User $user, ActionRequest $request): void
    {
        $this->initialisationFromGroup($task->group, $request);
        $this->handle($task, $user, $this->validatedData);
    }
}
