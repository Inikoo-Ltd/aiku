<?php

namespace App\Actions\Workspace\Task;

use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithWorkspaceEditAuthorisation;
use App\Enums\Workspace\TaskStatusEnum;
use App\Models\SysAdmin\Group;
use App\Models\Workspace\Task;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Http\RedirectResponse;

class StoreTask extends GrpAction
{
    use WithWorkspaceEditAuthorisation;

    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status'      => ['nullable', Rule::enum(TaskStatusEnum::class)],
            'assignee_id' => ['nullable', Rule::exists('employees', 'id')->where('group_id', app('group')->id)],
        ];
    }

    public function handle(Group $group, array $modelData, ?int $assignerId): Task
    {
        $modelData['group_id']    = $group->id;
        $modelData['assigner_id'] = $assignerId;
        $modelData['status']    ??= TaskStatusEnum::PENDING->value;

        return Task::create($modelData);
    }

    public function asController(ActionRequest $request): Task
    {
        $group = app('group');
        $this->initialisation($group, $request);

        return $this->handle($group, $this->validatedData, $request->user()->employee($group)?->id);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back()->with('success', __('Task created successfully.'));
    }
}
