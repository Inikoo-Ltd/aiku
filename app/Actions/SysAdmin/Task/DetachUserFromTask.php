<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:24:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Task;

use App\Models\SysAdmin\Task;
use Lorisleiva\Actions\Concerns\AsAction;

class DetachUserFromTask
{
    use AsAction;

    public function handle(Task $task, $user): void
    {
        $task->users()->detach($user->id);
    }

    public function asController(Task $task, $user): void
    {
        $this->handle($task, $user);
    }
}
