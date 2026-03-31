<?php

namespace App\Actions\HumanResources\ClockingMachineCoordinatePolicyRule;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Enums\HumanResources\ClockingMachine\ClockingPolicyModeEnum;
use App\Models\HumanResources\ClockingMachineCoordinatePolicy;
use App\Models\HumanResources\ClockingMachineCoordinatePolicyRule;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreClockingMachineCoordinatePolicyRule extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(ClockingMachineCoordinatePolicy $policy, array $modelData): ClockingMachineCoordinatePolicyRule
    {
        return $policy->rules()->create($modelData);
    }

    public function rules(): array
    {
        return [
            'day_of_week'   => ['nullable', 'integer', 'between:1,7'],
            'start_time'    => ['nullable', 'date_format:H:i:s'],
            'end_time'      => ['nullable', 'date_format:H:i:s', 'after:start_time'],
            'mode_override' => ['required', Rule::enum(ClockingPolicyModeEnum::class)],
            'is_active'     => ['sometimes', 'boolean'],
            'start_at'      => ['nullable', 'date'],
            'end_at'        => ['nullable', 'date', 'after_or_equal:start_at'],
        ];
    }

    public function asController(ClockingMachineCoordinatePolicy $clockingMachineCoordinatePolicy, ActionRequest $request): ClockingMachineCoordinatePolicyRule
    {
        $this->initialisation($clockingMachineCoordinatePolicy->organisation, $request);

        return $this->handle($clockingMachineCoordinatePolicy, $this->validatedData);
    }
}
