<?php

namespace App\Actions\Workspace\Task;

use App\Models\Workspace\Task;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\GrpAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use App\Enums\Workspace\TaskStatusEnum;

class UpdateTask extends GrpAction
{

    protected bool $statusOnly = false;

    public function authorize(ActionRequest $request): bool
    {
        if($this->asAction){
            return true;
        }

        $user = $request->user();
        
        if ($user->authTo('group-webmaster.edit')) {
            return true;
        }

        if (!$user->authTo('group-workspace.view')) {
            return false;
        }

        $task = $request->route('task');
        $employee = $user->employee(app('group'));

        $this->statusOnly = true;

        return $employee && $task->assignee_id === $employee->id;
    }

    public function rules(): array
    {
        if($this->statusOnly){
            return [
                'status' => ['required', 'string', Rule::in(TaskStatusEnum::class)],
            ];
        }

        return [
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status'      => ['sometimes', 'string', Rule::in(TaskStatusEnum::class)],
            'assignee_id' => ['nullable', Rule::exists('employees', 'id')->where('group_id', app('group')->id)],
        ];
    }

    public function asController(ActionRequest $request, Task $task)
    {
        $this->initialisation(app('group'), $request);

        return $this->handle($task, $this->validatedData);
    }

    public function handle(Task $task, array $modelData): Task
    {
        $task->update($modelData);

        return $task->fresh();
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back()->with('success', __('Task updated successfully'));
    }
}
