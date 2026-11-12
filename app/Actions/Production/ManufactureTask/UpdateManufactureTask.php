<?php

namespace App\Actions\Production\ManufactureTask;

use App\Actions\Production\Production\Hydrators\ProductionHydrateManufactureTasks;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateManufactureTasks;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateManufactureTasks;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use App\Models\HumanResources\Employee;
use App\Models\Production\ManufactureTask;
use App\Models\Production\WorkArea;
use App\Models\Production\Production;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UpdateManufactureTask extends OrgAction
{
    use WithActionUpdate;

    private ManufactureTask $manufactureTask;

    public function handle(ManufactureTask $manufactureTask, array $modelData): ManufactureTask
    {
        if (array_key_exists('auto_assign_to', $modelData)) {
            $data = $manufactureTask->data ?? [];
            if ($autoAssignTo = $this->autoAssignTarget($manufactureTask, $modelData['auto_assign_to'])) {
                $data['auto_assign'] = $autoAssignTo;
            } else {
                unset($data['auto_assign']);
            }
            $modelData = Arr::except($modelData, 'auto_assign_to') + ['data' => $data];
        }

        $manufactureTask = $this->update($manufactureTask, $modelData);
        if ($manufactureTask->wasChanged('operative_reward_terms') || $manufactureTask->wasChanged('operative_reward_allowance_type')) {
            GroupHydrateManufactureTasks::dispatch($manufactureTask->group);
            OrganisationHydrateManufactureTasks::dispatch($manufactureTask->organisation);
            ProductionHydrateManufactureTasks::dispatch($manufactureTask->production);
        }
        return $manufactureTask;
    }

    /**
     * HELP-4670: lines of this step go straight to a job for this person, or for the least busy artisan of this work area
     *
     * @return array{type: string, id: int}|null
     */
    private function autoAssignTarget(ManufactureTask $manufactureTask, ?string $value): ?array
    {
        if (!$value) {
            return null;
        }

        [$type, $id] = explode(':', $value);
        $exists = $type === 'employee'
            ? $id <= 32767 && Employee::where('id', $id)->where('organisation_id', $manufactureTask->organisation_id)->exists()
            : WorkArea::where('id', $id)->where('production_id', $manufactureTask->production_id)->exists();
        if (!$exists) {
            throw ValidationException::withMessages(['auto_assign_to' => __('Choose an artisan or a work area of this production')]);
        }

        return ['type' => $type, 'id' => (int) $id];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(["org-supervisor.{$this->organisation->id}", "productions_rd.{$this->production->id}.edit"]);
    }

    public function rules(): array
    {
        return [
            'code'        => [
                'sometimes',
                'alpha_dash',
                'max:64',
                new IUnique(
                    table: 'manufacture_tasks',
                    extraConditions: [
                        [
                            'column' => 'organisation_id',
                            'value'  => $this->organisation->id,
                        ],
                        [
                            'column'    => 'id',
                            'value'     => $this->manufactureTask->id,
                            'operator'  => '!='
                        ]

                    ]
                ),
            ],
            'name'                              => ['sometimes', 'string', 'max:255'],
            'task_materials_cost'               => ['sometimes', 'numeric', 'min:0'],
            'task_energy_cost'                  => ['sometimes', 'numeric', 'min:0'],
            'task_other_cost'                   => ['sometimes', 'numeric', 'min:0'],
            'task_work_cost'                    => ['sometimes', 'numeric', 'min:0'],
            'task_lower_target'                 => ['sometimes', 'numeric', 'min:0'],
            'task_upper_target'                 => ['sometimes', 'numeric', 'min:0'],
            'operative_reward_terms'            => ['sometimes', Rule::enum(ManufactureTaskOperativeRewardTermsEnum::class)],
            'operative_reward_allowance_type'   => ['sometimes', Rule::enum(ManufactureTaskOperativeRewardAllowanceTypeEnum::class)],
            'operative_reward_amount'           => ['sometimes', 'numeric', 'min:0'],
            'description'                       => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status'                            => ['sometimes', 'boolean'],
            'is_piece_rate'                     => ['sometimes', 'boolean'],
            'is_recorded_in_kg'                 => ['sometimes', 'boolean'],
            'is_quantity_only'                  => ['sometimes', 'boolean'],
            'auto_assign_to'                    => ['sometimes', 'nullable', 'string', 'regex:/^(employee|work_area):\d{1,9}$/'],
        ];
    }

    public function asController(Production $production, ManufactureTask $manufactureTask, ActionRequest $request): ManufactureTask
    {
        $this->manufactureTask = $manufactureTask;
        $this->initialisationFromProduction($manufactureTask->production, $request);

        return $this->handle(
            manufactureTask: $manufactureTask,
            modelData: $this->validatedData
        );
    }

    public function action(ManufactureTask $manufactureTask, $modelData): ManufactureTask
    {
        $this->asAction        = true;
        $this->manufactureTask = $manufactureTask;
        $this->initialisation($manufactureTask->organisation, $modelData);

        return $this->handle(
            manufactureTask: $manufactureTask,
            modelData: $this->validatedData
        );
    }
}
