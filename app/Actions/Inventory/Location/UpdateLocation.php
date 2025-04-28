<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 13:05:43 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\Inventory\Location;

use App\Actions\Inventory\Location\Search\LocationRecordSearch;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateLocations;
use App\Actions\Inventory\WarehouseArea\Hydrators\WarehouseAreaHydrateLocations;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateLocations;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateLocations;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Inventory\LocationResource;
use App\Models\Inventory\Location;
use App\Rules\IUnique;
use Lorisleiva\Actions\ActionRequest;

class UpdateLocation extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithWarehouseEditAuthorisation;

    private Location $location;

    public function handle(Location $location, array $modelData): Location
    {
        $location = $this->update($location, $modelData, ['data']);
        if ($location->wasChanged('status')) {
            GroupHydrateLocations::dispatch($location->group)->delay($this->hydratorsDelay);
            OrganisationHydrateLocations::dispatch($location->organisation)->delay($this->hydratorsDelay);
            WarehouseHydrateLocations::dispatch($location->warehouse)->delay($this->hydratorsDelay);

            if ($location->warehouse_area_id) {
                WarehouseAreaHydrateLocations::dispatch($location->warehouseArea)->delay($this->hydratorsDelay);
            }
        }

        LocationRecordSearch::dispatch($location);

        return $location;
    }

    public function rules(): array
    {
        $rules = [
            'code'               => [
                'sometimes',
                'required',
                'max:64',
                $this->strict ? 'alpha_dash' : 'string',
                new IUnique(
                    table: 'locations',
                    extraConditions: [
                        ['column' => 'warehouse_id', 'value' => $this->location->warehouse_id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->location->id
                        ]
                    ]
                ),
            ],
            'allow_stocks'       => ['sometimes', 'required', 'boolean'],
            'allow_fulfilment'   => ['sometimes', 'required', 'boolean'],
            'allow_dropshipping' => ['sometimes', 'required', 'boolean'],
            'max_weight'         => ['sometimes', 'nullable', 'numeric', 'min:0.1', 'max:1000000'],
            'max_volume'         => ['sometimes', 'nullable', 'numeric', 'min:0.1', 'max:1000000'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(Location $location, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Location
    {
        $this->strict = $strict;
        if (!$audit) {
            Location::disableAuditing();
        }
        $this->asAction       = true;
        $this->location       = $location;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($location->organisation, $modelData);

        return $this->handle($location, $this->validatedData);
    }

    public function asController(Location $location, ActionRequest $request): Location
    {
        $this->location = $location;

        $this->initialisationFromWarehouse($location->warehouse, $request);

        return $this->handle($location, $this->validatedData);
    }


    public function jsonResponse(Location $location): LocationResource
    {
        return new LocationResource($location);
    }
}
