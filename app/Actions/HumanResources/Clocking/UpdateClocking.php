<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 13:05:43 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\HumanResources\Clocking;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Inventory\LocationResource;
use App\Models\HumanResources\Clocking;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class UpdateClocking extends OrgAction
{
    use WithActionUpdate;
    use WithHumanResourcesEditAuthorisation;


    public function handle(Clocking $clocking, array $modelData): Clocking
    {
        return $this->update($clocking, $modelData, ['data']);
    }

    public function rules(): array
    {
        $rules = [
            'code' => ['sometimes', 'required', 'unique:locations', 'between:2,64', 'alpha_dash'],
        ];

        if (!$this->strict) {
            $rules['last_fetched_at'] = ['sometimes', 'date'];
        }

        return $rules;
    }

    public function action(Clocking $clocking, array $modelData, int $hydratorsDelay = 0, bool $strict = true): Clocking
    {
        $this->strict   = $strict;
        $this->asAction = true;
        $this->setRawAttributes($modelData);
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisation($clocking->organisation, $modelData);

        return $this->handle($clocking, $this->validatedData);
    }

    public function asController(Organisation $organisation, Clocking $clocking, ActionRequest $request): Clocking
    {
        $this->initialisation($clocking->organisation, $request);

        return $this->handle($clocking, $this->validatedData);
    }

    public function jsonResponse(Clocking $clocking): LocationResource
    {
        return new LocationResource($clocking);
    }
}
