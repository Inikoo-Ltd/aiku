<?php

namespace App\Actions\HumanResources\ClockingMachineCoordinatePolicy;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Enums\HumanResources\ClockingMachine\ClockingPolicyModeEnum;
use App\Models\HumanResources\ClockingMachineCoordinatePolicy;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreClockingMachineCoordinatePolicy extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(array $modelData): ClockingMachineCoordinatePolicy
    {
        return ClockingMachineCoordinatePolicy::query()->create($modelData);
    }

    public function rules(): array
    {
        return [
            'organisation_id'     => ['required', 'integer', 'exists:organisations,id'],
            'scope_type'          => ['required', 'string', Rule::in(['organisation', 'workplace', 'employee'])],
            'scope_id'            => ['required', 'integer', 'min:1'],
            'clocking_machine_id' => ['nullable', 'integer', 'exists:clocking_machines,id'],
            'mode'                => ['required', Rule::enum(ClockingPolicyModeEnum::class)],
            'is_active'           => ['sometimes', 'boolean'],
            'start_at'            => ['nullable', 'date'],
            'end_at'              => ['nullable', 'date', 'after_or_equal:start_at'],
            'reason'              => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function asController(ActionRequest $request): ClockingMachineCoordinatePolicy
    {
        $organisation = Organisation::query()->findOrFail((int) $request->input('organisation_id'));
        $this->initialisation($organisation, $request);

        return $this->handle($this->validatedData);
    }
}
