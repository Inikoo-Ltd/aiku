<?php

namespace App\Actions\Workspace\Task;

use App\Models\Workspace\Task;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use App\Actions\GrpAction;
// use App\Actions\Traits\Authorisations\WithWorkspaceEditAuthorisation;

class DeleteTask extends GrpAction
{
    // use WithWorkspaceEditAuthorisation;

    public function asController(ActionRequest $request, Task $task)
    {
        $this->initialisation(app('group'), $request);

        return $this->handle($task);
    }

    public function handle(Task $task): bool
    {
        return $task->delete();
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back()->with('success', __('Task deleted successfully'));
    }
}
