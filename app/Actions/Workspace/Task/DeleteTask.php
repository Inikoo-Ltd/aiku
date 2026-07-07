<?php

namespace App\Actions\Workspace\Task;

use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithWorkspaceEditAuthorisation;
use App\Models\Workspace\Task;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class DeleteTask extends GrpAction
{
    use WithWorkspaceEditAuthorisation;

    public function handle(Task $task): bool
    {
        return $task->delete();
    }

    public function asController(ActionRequest $request, Task $task): bool
    {
        $this->initialisation(app('group'), $request);

        return $this->handle($task);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back()->with('success', __('Task deleted successfully.'));
    }
}
