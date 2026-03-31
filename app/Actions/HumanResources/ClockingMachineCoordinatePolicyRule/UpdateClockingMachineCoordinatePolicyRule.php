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
            'start_time'    => ['nullable', 'date_format:H:i:s'],
            'end_time'      => ['nullable', 'date_format:H:i:s', 'after:start_time'],
            'mode_override' => ['sometimes', 'required', Rule::enum(ClockingPolicyModeEnum::class)],
            'is_active'     => ['sometimes', 'boolean'],
            'start_at'      => ['nullable', 'date'],
            'end_at'        => ['nullable', 'date', 'after_or_equal:start_at'],
        ];
    }

    public function asController(ClockingMachineCoordinatePolicyRule $clockingMachineCoordinatePolicyRule, ActionRequest $request): ClockingMachineCoordinatePolicyRule
    {
        $this->initialisation($clockingMachineCoordinatePolicyRule->policy->organisation, $request);

        return $this->handle($clockingMachineCoordinatePolicyRule, $this->validatedData);
    }
}
