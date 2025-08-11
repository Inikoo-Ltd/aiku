<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Aug 2024 17:52:21 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\LocationOrgStock;

use App\Actions\Inventory\Location\Hydrators\LocationHydrateStocks;
use App\Actions\Inventory\Location\Hydrators\LocationHydrateStockValue;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateLocations;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations;
use App\Actions\Maintenance\Dispatching\RepairOrgStockMissingLocationIds;
use App\Actions\OrgAction;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Http\Resources\Inventory\LocationOrgStockResource;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Validation\Validator;

class StoreLocationOrgStock extends OrgAction
{
    use WithLocationOrgStockActionAuthorisation;

    private Location $location;
    private OrgStock $orgStock;


    public function handle(OrgStock $orgStock, Location $location, array $modelData): LocationOrgStock
    {
        data_set($modelData, 'group_id', $location->group_id);
        data_set($modelData, 'organisation_id', $location->organisation_id);
        data_set($modelData, 'warehouse_id', $location->warehouse_id);
        data_set($modelData, 'warehouse_area_id', $location->warehouse_area_id);
        data_set($modelData, 'org_stock_id', $orgStock->id);


        $locationStock = $location->locationOrgStocks()->create($modelData);
        RepairOrgStockMissingLocationIds::dispatch($orgStock)->delay($this->hydratorsDelay);
        LocationHydrateStocks::dispatch($location)->delay($this->hydratorsDelay);
        LocationHydrateStockValue::dispatch($location)->delay($this->hydratorsDelay);
        OrgStockHydrateLocations::dispatch($orgStock)->delay($this->hydratorsDelay);
        OrgStockHydrateQuantityInLocations::dispatch($orgStock)->delay($this->hydratorsDelay);

        return $locationStock;
    }

    public function rules(): array
    {
        $rules = [
            'data'               => ['sometimes', 'array'],
            'settings'           => ['sometimes', 'array'],
            'notes'              => ['sometimes', 'nullable', 'string', 'max:255'],
            'picking_priority'   => ['sometimes', 'integer'],
            'type'               => ['sometimes', Rule::enum(LocationStockTypeEnum::class)],
        ];

        if (!$this->strict) {
            $rules['audited_at']         = ['date'];
            $rules['quantity']           = ['required', 'numeric'];
            $rules['fetched_at']         = ['required', 'date'];
            $rules['source_stock_id']    = ['sometimes', 'string', 'max:255'];
            $rules['source_location_id'] = ['sometimes', 'string', 'max:255'];
        }

        return $rules;
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        if ($this->location->organisation_id != $this->orgStock->organisation_id) {
            $validator->errors()->add('location_org_stock', 'Location / stock organisation does not match');
        }


        if (LocationOrgStock::where('location_id', $this->location->id)->where('org_stock_id', $this->orgStock->id)
                ->count() > 0) {
            $validator->errors()->add('location_org_stock', __('This stock is already assigned to this location'));
        }


        if ($this->strict and $this->has('type') and $this->get('type') == LocationStockTypeEnum::PICKING->value) {
            if (LocationOrgStock::where('type', LocationStockTypeEnum::PICKING->value)->where('org_stock_id', $this->orgStock->id)
                    ->count() > 0) {
                $validator->errors()->add('type', __('This stock can have one picking only'));
            }
        }

    }

    public function asController(OrgStock $orgStock, Location $location, ActionRequest $request, int $hydratorsDelay = 0, bool $strict = true): void
    {
        $this->location = $location;
        $this->orgStock = $orgStock;
        $this->initialisation($orgStock->organisation, $request);

        $this->handle($orgStock, $location, $this->validatedData);
    }

    public function action(OrgStock $orgStock, Location $location, array $modelData, int $hydratorsDelay = 0, bool $strict = true): LocationOrgStock
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $this->location       = $location;
        $this->orgStock       = $orgStock;
        $this->initialisation($orgStock->organisation, $modelData);

        return $this->handle($orgStock, $location, $this->validatedData);
    }

    public function jsonResponse(LocationOrgStock $locationStock): LocationOrgStockResource
    {
        return new LocationOrgStockResource($locationStock);
    }


}
