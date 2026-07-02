<?php

namespace App\Actions\Workspace\Task;

use App\Models\Workspace\Task;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteTask
{
    use AsAction;

    public function asController(ActionRequest $request, Task $task)
    {
        $this->handle($task);

        return redirect()->back()->with('success', __('Task deleted successfully.'));
    }

    public function handle(Task $task): bool
    {
        return $task->delete();
    }
}
