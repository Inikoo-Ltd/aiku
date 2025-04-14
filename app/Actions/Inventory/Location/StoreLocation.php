<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 12:46:32 Malaysia Time, Kuala Lumpur, Malaysia
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
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreLocation extends OrgAction
{
    use WithNoStrictRules;
    use WithWarehouseEditAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(WarehouseArea|Warehouse $parent, array $modelData): Location
    {
        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'organisation_id', $parent->organisation_id);

        if (class_basename($parent::class) == 'WarehouseArea') {
            $modelData['warehouse_id'] = $parent->warehouse_id;
            $organisation              = $parent->warehouse->organisation;
        } else {
            $organisation = $parent->organisation;
        }

        $location = DB::transaction(function () use ($parent, $modelData) {
            /** @var Location $location */
            $location = $parent->locations()->create($modelData);
            $location->stats()->create();
            $location->updateQuietly(['barcode' => $location->slug]);
            return $location;
        });

        GroupHydrateLocations::dispatch($organisation->group)->delay($this->hydratorsDelay);
        OrganisationHydrateLocations::dispatch($organisation)->delay($this->hydratorsDelay);
        WarehouseHydrateLocations::dispatch($location->warehouse)->delay($this->hydratorsDelay);

        if ($location->warehouse_area_id) {
            WarehouseAreaHydrateLocations::dispatch($location->warehouseArea)->delay($this->hydratorsDelay);
        }

        LocationRecordSearch::dispatch($location);

        return $location;
    }

    public function rules(): array
    {
        $rules = [
            'code'       => [
                'required',
                'max:64',
                'alpha_dash',
                new IUnique(
                    table: 'locations',
                    extraConditions: [
                        ['column' => 'warehouse_id', 'value' => $this->warehouse->id],
                    ]
                ),
            ],
            'data' => ['sometimes', 'nullable', 'array'],
            'max_weight' => ['sometimes', 'nullable', 'numeric', 'min:0.1', 'max:1000000'],
            'max_volume' => ['sometimes', 'nullable', 'numeric', 'min:0.1', 'max:1000000'],
        ];

        if (!$this->strict) {
            $rules['code']       = [
                'required',
                'max:64',
                'string',
            ];

            $rules = $this->noStrictStoreRules($rules);

        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function inWarehouse(Warehouse $warehouse, ActionRequest $request): Location
    {
        $this->warehouse = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $this->validatedData);
    }


    /**
     * @throws \Throwable
     */
    public function inWarehouseArea(WarehouseArea $warehouseArea, ActionRequest $request): Location
    {
        $this->warehouse = $warehouseArea->warehouse;
        $this->initialisationFromWarehouse($warehouseArea->warehouse, $request);

        return $this->handle($warehouseArea, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function action(WarehouseArea|Warehouse $parent, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Location
    {
        if (!$audit) {
            Location::disableAuditing();
        }

        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        if (class_basename($parent::class) == 'WarehouseArea') {
            $this->warehouse = $parent->warehouse;
        } else {
            $this->warehouse = $parent;
        }

        $this->initialisationFromWarehouse($this->warehouse, $modelData);

        return $this->handle($parent, $this->validatedData);
    }

    public function htmlResponse(Location $location): RedirectResponse
    {
        if (!$location->warehouse_area_id) {
            return Redirect::route('grp.org.warehouses.show.infrastructure.locations.show', [
                $location->organisation->slug,
                $location->warehouse->slug,
                $location->slug
            ]);
        } else {
            return Redirect::route('grp.org.warehouses.show.infrastructure.warehouse_areas.show.locations.show', [
                $location->organisation->slug,
                $location->warehouse->slug,
                $location->warehouseArea->slug,
                $location->slug
            ]);
        }
    }

}
