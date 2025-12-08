<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:24:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Task;

use App\Models\SysAdmin\Task;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class DetachTaskFromUser
{
    use AsAction;

    public function handle(User $user, Task $task): void
    {
        $user->tasks()->detach($task->id);
    }

    public function asController(User $user, Task $task): void
    {
        $this->handle($user, $task);
    }
}
