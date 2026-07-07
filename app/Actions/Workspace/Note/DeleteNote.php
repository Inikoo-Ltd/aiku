<?php

namespace App\Actions\Workspace\Note;

use App\Actions\GrpAction;
use App\Models\Workspace\Note;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class DeleteNote extends GrpAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $user = $request->user();

        if (!$user->authTo(['group-webmaster.view', 'group-webmaster.edit'])) {
            return false;
        }

        /** @var Note $note */
        $note     = $request->route('note');
        $employee = $user->employee(app('group'));

        return $employee && $note->employee_id === $employee->id;
    }

    public function handle(Note $note): bool
    {
        return $note->delete();
    }

    public function asController(ActionRequest $request, Note $note): bool
    {
        $this->initialisation(app('group'), $request);

        return $this->handle($note);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back()->with('success', __('Note deleted successfully.'));
    }
}
