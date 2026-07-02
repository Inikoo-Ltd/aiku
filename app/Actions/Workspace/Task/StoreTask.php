<?php

namespace App\Actions\Workspace\Task;

use App\Models\Workspace\Task;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreTask
{
    use AsAction;

    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status'      => ['nullable', 'string', 'in:Pending,Working on,Ready,Can\'t be done'],
            'assignee_id' => ['nullable', 'exists:employees,id'],
        ];
    }

    public function asController(ActionRequest $request)
    {
        $this->handle(
            $request->validated(),
            $request->user()->employee?->id
        );

        return redirect()->back()->with('success', __('Task created successfully.'));
    }

    public function handle(array $data, ?int $assignerId = null): Task
    {
        return Task::create([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'status'      => $data['status'] ?? 'Pending',
            'assignee_id' => $data['assignee_id'] ?? null,
            'assigner_id' => $assignerId,
        ]);
    }
}
