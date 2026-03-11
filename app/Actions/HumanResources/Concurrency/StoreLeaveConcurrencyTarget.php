<?php

namespace App\Actions\HumanResources\Concurrency;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Enums\HumanResources\Concurrency\LeaveConcurrencyTargetRoleEnum;
use App\Models\HumanResources\LeaveConcurrencyRule;
use App\Models\HumanResources\LeaveConcurrencyTarget;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreLeaveConcurrencyTarget extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(LeaveConcurrencyRule $leaveConcurrencyRule, array $modelData): LeaveConcurrencyTarget
    {
        $modelData['leave_concurrency_rule_id'] = $leaveConcurrencyRule->id;

        $targetType = $modelData['target_type'];
        $targetId = $modelData['target_id'];

        $modelClass = match ($targetType) {
            'Employee'  => \App\Models\HumanResources\Employee::class,
            'LeaveType' => \App\Models\HumanResources\LeaveType::class,
            default     => null,
        };

        if (!$modelClass || !$modelClass::where('id', $targetId)->exists()) {
            abort(422, __('Target not found.'));
        }

        return LeaveConcurrencyTarget::query()->create($modelData);
    }

    public function rules(): array
    {
        return [
            'target_type' => ['required', 'string', Rule::in(['Employee', 'LeaveType'])],
            'target_id'   => ['required', 'numeric'],
            'role'        => ['sometimes', 'nullable', 'string', Rule::enum(LeaveConcurrencyTargetRoleEnum::class)],
        ];
    }

    public function asController(Organisation $organisation, LeaveConcurrencyRule $leaveConcurrencyRule, ActionRequest $request): LeaveConcurrencyTarget
    {
        $this->initialisation($organisation, $request);

        return $this->handle($leaveConcurrencyRule, $this->validatedData);
    }

    public function action(LeaveConcurrencyRule $leaveConcurrencyRule, array $modelData): LeaveConcurrencyTarget
    {
        $this->asAction = true;
        $this->initialisation($leaveConcurrencyRule->organisation, $modelData);

        return $this->handle($leaveConcurrencyRule, $this->validatedData);
    }

    public function htmlResponse(LeaveConcurrencyTarget $leaveConcurrencyTarget): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Target successfully added to leave concurrency rule.'),
        ]);

        return Redirect::back();
    }
}
