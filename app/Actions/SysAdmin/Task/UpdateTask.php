<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Jun 2024 20:18:32 Central European Summer Time, Abu Dhabi Airport
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Task;

use App\Models\SysAdmin\Task;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateTask
{
    use AsAction;
    use WithAttributes;

    public function handle(Task $task, $modelData): Task
    {
        $task->update($modelData);

        return $task;
    }

    public function rules(): array
    {
        return [
            'organisation_id' => ['sometimes', 'exists:organisations,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
        ];
    }

    public function asController(ActionRequest $request, Task $task): Task
    {

        return $this->handle($task, $this->validatedData);
    }
}
