<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 11:34:13 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Workplace;

use App\Actions\HumanResources\Workplace\Search\WorkplaceRecordSearch;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateWorkplaces;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\HumanResources\Workplace\WorkplaceTypeEnum;
use App\Http\Resources\HumanResources\WorkplaceResource;
use App\Models\HumanResources\Workplace;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateWorkplace extends OrgAction
{
    use WithActionUpdate;
    use WithModelAddressActions;
    use WithHumanResourcesEditAuthorisation;


    private Workplace $workplace;

    public function handle(Workplace $workplace, array $modelData): Workplace
    {
        $addressData = Arr::get($modelData, 'address');
        Arr::forget($modelData, 'address');

        $workplace = $this->update($workplace, $modelData, ['data']);

        if (Arr::hasAny($workplace->getChanges(), ['type'])) {
            OrganisationHydrateWorkplaces::run($workplace->organisation);
        }
        $workplace = $this->updateModelAddress($workplace, $addressData);


        WorkplaceRecordSearch::dispatch($workplace);

        return $workplace;
    }


    public function rules(): array
    {
        return [
            'name'        => [
                'sometimes',
                'required',
                'max:255',
                new IUnique(
                    table: 'workplaces',
                    extraConditions: [
                        [
                            'column' => 'group_id',
                            'value'  => $this->organisation->group_id,

                        ],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->workplace->id
                        ],
                    ]
                ),
            ],
            'type'        => ['sometimes', Rule::Enum(WorkplaceTypeEnum::class)],
            'timezone_id' => ['sometimes'],
            'address'     => ['sometimes', 'required', new ValidAddress()]
        ];
    }

    public function asController(Organisation $organisation, Workplace $workplace, ActionRequest $request): Workplace
    {
        $this->workplace = $workplace;
        $this->initialisation($workplace->organisation, $request);

        return $this->handle(workplace: $workplace, modelData: $this->validatedData);
    }

    public function jsonResponse(Workplace $workplace): WorkplaceResource
    {
        return new WorkplaceResource($workplace);
    }
}
