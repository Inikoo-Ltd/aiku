<?php

namespace App\Actions\Workspace\Note;

use App\Models\Workspace\Note;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreNote
{
    use AsAction;

    public function rules(): array
    {
        return [
            'title'   => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
        ];
    }

    public function asController(ActionRequest $request)
    {
        $this->handle(
            $request->validated(),
            $request->user()->employee?->id
        );

        return redirect()->back()->with('success', __('Note created successfully.'));
    }

    public function handle(array $data, ?int $employeeId): Note
    {
        return Note::create([
            'title'       => $data['title'],
            'content'     => $data['content'] ?? null,
            'employee_id' => $employeeId,
        ]);
    }
}
