<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 May 2024 10:36:02 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTask;

use App\Actions\OrgAction;
use App\Actions\Production\ManufactureTask\Hydrators\ManufactureTaskHydrateUniversalSearch;
use App\Actions\Production\Production\Hydrators\ProductionHydrateManufactureTasks;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateManufactureTasks;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateManufactureTasks;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreManufactureTask extends OrgAction
{
    use AsAction;

    public function handle(Production $production, $modelData): ManufactureTask
    {
        data_set($modelData, 'group_id', $production->group_id);
        data_set($modelData, 'organisation_id', $production->organisation_id);

        /** @var ManufactureTask $manufactureTask */
        $manufactureTask = $production->manufactureTasks()->create($modelData);
        $manufactureTask->stats()->create();

        ManufactureTaskHydrateUniversalSearch::dispatch($manufactureTask);
        GroupHydrateManufactureTasks::dispatch($manufactureTask->group);
        OrganisationHydrateManufactureTasks::dispatch($manufactureTask->organisation);
        ProductionHydrateManufactureTasks::dispatch($production);

        return $manufactureTask;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("productions_rd.{$this->production->id}.edit");
    }

    public function htmlResponse(ManufactureTask $manufactureTask): RedirectResponse
    {
        $production = $manufactureTask->production;
        $organisation = $manufactureTask->organisation;

        return Redirect::route('grp.org.productions.show.crafts.manufacture_tasks.index', [$organisation, $production]);
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'alpha_dash',
                'max:64',
                new IUnique(
                    table: 'manufacture_tasks',
                    extraConditions: [
                        ['column' => 'organisation_id', 'value' => $this->organisation->id],
                    ]
                ),
            ],
            'name' => ['required', 'string', 'max:255'],
            'task_materials_cost' => ['required', 'numeric', 'min:0'],
            'task_energy_cost' => ['required', 'numeric', 'min:0'],
            'task_other_cost' => ['required', 'numeric', 'min:0'],
            'task_work_cost' => ['required', 'numeric', 'min:0'],
            'task_lower_target' => ['required', 'numeric', 'min:0'],
            'task_upper_target' => ['required', 'numeric', 'min:0'],
            'operative_reward_terms' => ['required', Rule::enum(ManufactureTaskOperativeRewardTermsEnum::class)],
            'operative_reward_allowance_type' => ['required', Rule::enum(ManufactureTaskOperativeRewardAllowanceTypeEnum::class)],
            'operative_reward_amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    //     public function afterValidator($validator)
    // {
    //     dd($validator);
    // }

    public function action(Production $production, array $modelData): ManufactureTask
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, $modelData);

        return $this->handle($production, $this->validatedData);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): ManufactureTask
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData);
    }
}
