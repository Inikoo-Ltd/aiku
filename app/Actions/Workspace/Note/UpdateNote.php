<?php

namespace App\Actions\Workspace\Note;

use App\Models\Workspace\Note;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateNote
{
    use AsAction;

    public function rules(): array
    {
        return [
            'title'   => ['sometimes', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
        ];
    }

    public function asController(ActionRequest $request, Note $note)
    {
        $this->handle($note, $request->validated());

        return redirect()->back()->with('success', __('Note updated successfully.'));
    }

    public function handle(Note $note, array $data): Note
    {
        $note->update($data);

        return $note->fresh();
    }
}
