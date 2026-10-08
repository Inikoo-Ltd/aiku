<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Models\HumanResources\Leave;
use App\Models\SysAdmin\Organisation;
use App\Notifications\LeaveCoverNotification;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateLeaveCover extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    private Leave $leave;

    /**
     * @param array{cover_employee_id?: int|null, cover_has_permissions?: bool} $modelData
     */
    public function handle(Leave $leave, array $modelData): Leave
    {
        $previousCover = $leave->coverEmployee;
        $coverEmployeeId = $modelData['cover_employee_id'] ?? null;

        $leave->update([
            'cover_employee_id'     => $coverEmployeeId,
            'cover_has_permissions' => $coverEmployeeId && ($modelData['cover_has_permissions'] ?? false),
        ]);
        $leave->load('coverEmployee');

        foreach (collect([$previousCover, $leave->coverEmployee])->filter()->unique('id') as $coverEmployee) {
            SyncLeaveCoverRoles::run($coverEmployee);
        }

        if ($leave->coverEmployee && $leave->coverEmployee->id !== $previousCover?->id) {
            Notification::send(
                $leave->coverEmployee->users()->wherePivot('status', true)->get(),
                new LeaveCoverNotification($leave)
            );
        }

        return $leave;
    }

    public static function coverRules(Organisation $organisation, int $absentEmployeeId): array
    {
        return [
            'cover_employee_id'     => [
                'nullable',
                'integer',
                Rule::notIn([$absentEmployeeId]),
                Rule::exists('employees', 'id')
                    ->where('organisation_id', $organisation->id)
                    ->where('state', EmployeeStateEnum::WORKING->value),
            ],
            'cover_has_permissions' => ['sometimes', 'boolean'],
        ];
    }

    public function rules(): array
    {
        return self::coverRules($this->organisation, $this->leave->employee_id);
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->get('cover_employee_id') && $this->leave->status !== LeaveStatusEnum::APPROVED) {
            $validator->errors()->add('cover_employee_id', __('Only approved leave can be covered.'));
        }
    }

    public function asController(Organisation $organisation, Leave $leave, ActionRequest $request): Leave
    {
        abort_unless($leave->organisation_id === $organisation->id, 404);
        $this->leave = $leave;
        $this->initialisation($organisation, $request);

        return $this->handle($leave, $this->validatedData);
    }

    public function htmlResponse(Leave $leave): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => $leave->coverEmployee
                ? __(':cover is covering for :name.', ['cover' => $leave->coverEmployee->contact_name, 'name' => $leave->employee_name])
                : __('Cover ended for :name.', ['name' => $leave->employee_name]),
        ]);
    }
}
