<?php

namespace App\Actions\Workspace\Note;

use App\Actions\GrpAction;
use App\Models\Workspace\Note;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class UpdateNote extends GrpAction
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

    public function rules(): array
    {
        return [
            'title'   => ['sometimes', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
        ];
    }

    public function handle(Note $note, array $modelData): Note
    {
        $note->update($modelData);

        return $note->fresh();
    }

    public function asController(ActionRequest $request, Note $note): Note
    {
        $this->initialisation(app('group'), $request);

        return $this->handle($note, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back()->with('success', __('Note updated successfully.'));
    }
}
