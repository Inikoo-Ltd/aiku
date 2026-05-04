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
        $rules = $modelData['rules'] ?? [];
        unset($modelData['rules']);

        $policy = ClockingMachineCoordinatePolicy::query()->create($modelData);

        if (($modelData['mode'] ?? null) === ClockingPolicyModeEnum::HYBRID->value && is_array($rules) && count($rules) > 0) {
            $policy->rules()->createMany(
                collect($rules)->map(fn ($rule) => [
                    'day_of_week'   => $rule['day_of_week'] ?? null,
                    'mode_override' => $rule['mode_override'] ?? ClockingPolicyModeEnum::ONSITE->value,
                    'is_active'     => (bool) ($rule['is_active'] ?? true),
                ])->values()->all()
            );
        }

        return $policy;
    }

    public function rules(): array
    {
        $scopeUniqueRule = Rule::unique('clocking_machine_coordinate_policies', 'scope_id')
            ->where(function ($query) {
                $query
                    ->where('organisation_id', (int) request()->input('organisation_id'))
                    ->where('scope_type', (string) request()->input('scope_type'));

                $clockingMachineId = request()->input('clocking_machine_id');
                if ($clockingMachineId) {
                    $query->where('clocking_machine_id', (int) $clockingMachineId);
                } else {
                    $query->whereNull('clocking_machine_id');
                }
            });

        return [
            'organisation_id'     => ['required', 'integer', 'exists:organisations,id'],
            'scope_type'          => ['required', 'string', Rule::in(['organisation', 'workplace', 'employee'])],
            'scope_id'            => ['required', 'integer', 'min:1', $scopeUniqueRule],
            'clocking_machine_id' => ['nullable', 'integer', 'exists:clocking_machines,id'],
            'mode'                => ['required', Rule::enum(ClockingPolicyModeEnum::class)],
            'is_active'           => ['sometimes', 'boolean'],
            'start_at'            => ['nullable', 'date'],
            'end_at'              => ['nullable', 'date', 'after_or_equal:start_at'],
            'reason'              => ['nullable', 'string', 'max:5000'],
            'rules'               => ['nullable', 'array'],
            'rules.*.day_of_week' => ['required_with:rules', 'integer', 'between:1,7'],
            'rules.*.mode_override' => ['required_with:rules', Rule::enum(ClockingPolicyModeEnum::class)],
            'rules.*.is_active'   => ['sometimes', 'boolean'],
        ];
    }

    public function asController(ActionRequest $request): ClockingMachineCoordinatePolicy
    {
        $organisation = Organisation::query()->findOrFail((int) $request->input('organisation_id'));
        $this->initialisation($organisation, $request);

        return $this->handle($this->validatedData);
    }

    public function htmlResponse(): void
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Clocking policy successfully created.'),
        ]);
    }
}
