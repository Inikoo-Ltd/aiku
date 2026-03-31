<?php

namespace App\Actions\HumanResources\ClockingMachineCoordinatePolicy;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Enums\HumanResources\ClockingMachine\ClockingPolicyModeEnum;
use App\Models\HumanResources\ClockingMachineCoordinatePolicy;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateClockingMachineCoordinatePolicy extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(ClockingMachineCoordinatePolicy $policy, array $modelData): ClockingMachineCoordinatePolicy
    {
        $policy->update($modelData);

        return $policy->refresh();
    }

    public function rules(): array
    {
        return [
            'scope_type'          => ['sometimes', 'required', 'string', Rule::in(['organisation', 'workplace', 'employee'])],
            'scope_id'            => ['sometimes', 'required', 'integer', 'min:1'],
            'clocking_machine_id' => ['nullable', 'integer', 'exists:clocking_machines,id'],
            'mode'                => ['sometimes', 'required', Rule::enum(ClockingPolicyModeEnum::class)],
            'is_active'           => ['sometimes', 'boolean'],
            'start_at'            => ['nullable', 'date'],
            'end_at'              => ['nullable', 'date', 'after_or_equal:start_at'],
            'reason'              => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function asController(ClockingMachineCoordinatePolicy $clockingMachineCoordinatePolicy, ActionRequest $request): ClockingMachineCoordinatePolicy
    {
        $this->initialisation($clockingMachineCoordinatePolicy->organisation, $request);

        return $this->handle($clockingMachineCoordinatePolicy, $this->validatedData);
    }
}
