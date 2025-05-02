<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 26 Aug 2022 00:49:45 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\HumanResources\ClockingMachine;

use App\Actions\HumanResources\ClockingMachine\Search\ClockingMachineRecordSearch;
use App\Actions\HumanResources\Workplace\Hydrators\WorkplaceHydrateClockingMachines;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateClockingMachines;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateClockingMachines;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum;
use App\Http\Resources\HumanResources\ClockingMachineResource;
use App\Models\HumanResources\ClockingMachine;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateClockingMachine extends OrgAction
{
    use WithActionUpdate;
    use WithHumanResourcesEditAuthorisation;


    private ClockingMachine $clockingMachine;

    public function handle(ClockingMachine $clockingMachine, array $modelData): ClockingMachine
    {
        $clockingMachine = $this->update($clockingMachine, $modelData, ['data']);

        if ($clockingMachine->wasChanged(['type', 'status'])) {
            OrganisationHydrateClockingMachines::dispatch($clockingMachine->organisation)->delay($this->hydratorsDelay);
            GroupHydrateClockingMachines::dispatch($clockingMachine->group)->delay($this->hydratorsDelay);
            WorkplaceHydrateClockingMachines::dispatch($clockingMachine->workplace)->delay($this->hydratorsDelay);
        }


        ClockingMachineRecordSearch::dispatch($clockingMachine);


        return $clockingMachine;
    }


    public function rules(): array
    {
        $rules = [
            'name' => [
                'sometimes',
                'required',
                'max:255',
                new IUnique(
                    table: 'clocking_machines',
                    extraConditions: [
                        [
                            'column' => 'organisation_id',
                            'value'  => $this->organisation->id,

                        ],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->clockingMachine->id
                        ],
                    ]
                ),

            ],
            'type' => ['required', Rule::enum(ClockingMachineTypeEnum::class)],

        ];

        if (!$this->strict) {
            $rules['source_id']       = ['sometimes', 'string', 'max:255'];
            $rules['last_fetched_at'] = ['sometimes', 'date'];
        }

        return $rules;
    }

    public function asController(Organisation $organisation, ClockingMachine $clockingMachine, ActionRequest $request): ClockingMachine
    {
        $this->clockingMachine = $clockingMachine;
        $this->initialisation($clockingMachine->organisation, $request);

        return $this->handle($clockingMachine, $this->validatedData);
    }

    public function action(ClockingMachine $clockingMachine, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): ClockingMachine
    {
        $this->strict = $strict;
        if (!$audit) {
            ClockingMachine::disableAuditing();
        }
        $this->asAction        = true;
        $this->clockingMachine = $clockingMachine;
        $this->hydratorsDelay  = $hydratorsDelay;
        $this->initialisation($clockingMachine->organisation, $modelData);

        return $this->handle($clockingMachine, $this->validatedData);
    }

    public function jsonResponse(ClockingMachine $clockingMachine): ClockingMachineResource
    {
        return new ClockingMachineResource($clockingMachine);
    }
}
