<?php

namespace App\Actions\Workspace\Note;

use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithWorkspaceAuthorisation;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Group;
use App\Models\Workspace\Note;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StoreNote extends GrpAction
{
    use WithWorkspaceAuthorisation;

    public function rules(): array
    {
        return [
            'title'   => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
        ];
    }

    public function handle(Group $group, Employee $employee, array $modelData): Note
    {
        $modelData['group_id']    = $group->id;
        $modelData['employee_id'] = $employee->id;

        return Note::create($modelData);
    }

    public function asController(ActionRequest $request): Note
    {
        $group = app('group');
        $this->initialisation($group, $request);

        $employee = $request->user()->employee($group);

        if (!$employee) {
            throw ValidationException::withMessages(['title' => __('No employee record found for this user.')]);
        }

        return $this->handle($group, $employee, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back()->with('success', __('Note created successfully.'));
    }
}
