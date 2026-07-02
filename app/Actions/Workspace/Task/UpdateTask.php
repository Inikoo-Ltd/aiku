<?php

namespace App\Actions\Workspace\Task;

use App\Models\Workspace\Task;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateTask
{
    use AsAction;

    public function rules(): array
    {
        return [
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status'      => ['sometimes', 'string', 'in:Pending,Working on,Ready,Can\'t be done'],
            'assignee_id' => ['nullable', 'exists:employees,id'],
        ];
    }

    public function asController(ActionRequest $request, Task $task)
    {
        $this->handle($task, $request->validated());

        return redirect()->back()->with('success', __('Task updated successfully.'));
    }

    public function handle(Task $task, array $data): Task
    {
        $task->update($data);

        return $task->fresh();
    }
}
