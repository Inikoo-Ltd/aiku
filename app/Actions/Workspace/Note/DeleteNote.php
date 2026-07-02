<?php

namespace App\Actions\Workspace\Note;

use App\Models\Workspace\Note;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteNote
{
    use AsAction;

    public function asController(ActionRequest $request, Note $note)
    {
        $this->handle($note);

        return redirect()->back()->with('success', __('Note deleted successfully.'));
    }

    public function handle(Note $note): bool
    {
        return $note->delete();
    }
}
