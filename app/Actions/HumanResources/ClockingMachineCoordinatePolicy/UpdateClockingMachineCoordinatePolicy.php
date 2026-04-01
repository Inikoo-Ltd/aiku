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

        if (array_key_exists('mode', $modelData) && (string) $modelData['mode'] !== ClockingPolicyModeEnum::HYBRID->value) {
            $policy->rules()->delete();
        }

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

    public function asController(ClockingMachineCoordinatePolicy $policy, ActionRequest $request): ClockingMachineCoordinatePolicy
    {
        $this->initialisation($policy->organisation, $request);

        return $this->handle($policy, $this->validatedData);
    }

    public function htmlResponse(): void
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Clocking policy successfully updated.'),
        ]);
    }
}
