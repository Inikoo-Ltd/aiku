<?php

namespace App\Actions\HumanResources\ClockingMachineCoordinatePolicyRule;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Enums\HumanResources\ClockingMachine\ClockingPolicyModeEnum;
use App\Models\HumanResources\ClockingMachineCoordinatePolicyRule;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateClockingMachineCoordinatePolicyRule extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(ClockingMachineCoordinatePolicyRule $ruleModel, array $modelData): ClockingMachineCoordinatePolicyRule
    {
        $ruleModel->update($modelData);

        return $ruleModel->refresh();
    }

    public function rules(): array
    {
        return [
            'day_of_week'   => ['nullable', 'integer', 'between:1,7'],
            'mode_override' => ['sometimes', 'required', Rule::enum(ClockingPolicyModeEnum::class)],
            'is_active'     => ['sometimes', 'boolean'],
        ];
    }

    public function asController(ClockingMachineCoordinatePolicyRule $policyRule, ActionRequest $request): ClockingMachineCoordinatePolicyRule
    {
        $this->initialisation($policyRule->policy->organisation, $request);

        return $this->handle($policyRule, $this->validatedData);
    }
    public function htmlResponse(): void
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Clocking policy rule successfully updated.'),
        ]);
    }
}
