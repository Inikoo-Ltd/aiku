<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 11:39:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Inventory\LocationOrgStock\DeleteLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock;
use App\Actions\OrgAction;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncOrgStockLocations extends OrgAction
{
    use AsAction;

    /**
     * @throws \Throwable
     */
    public function handle(OrgStock $orgStock, array $modelData): array
    {
        $locationsData = $modelData['locationsData'];

        $oldLocations = $orgStock->locationOrgStocks()->pluck('location_id')->toArray();
        $newLocations = [];

        foreach ($locationsData as $locationID => $locationOrgStockData) {

            if ($locationOrgStock = LocationOrgStock::where('org_stock_id', $orgStock->id)->where('location_id', $locationID)->first()) {
                UpdateLocationOrgStock::make()->action($locationOrgStock, $locationOrgStockData, $this->hydratorsDelay, $this->strict, audit: false);
            } else {
                /** @var Location $location */
                $location = Location::find($locationID);
                StoreLocationOrgStock::make()->action($orgStock, $location, $locationOrgStockData, $this->hydratorsDelay, $this->strict);
            }
            $newLocations[] = $locationID;
        }



        foreach (array_diff($oldLocations, $newLocations) as $locationID) {

            $locationOrgStock = LocationOrgStock::where('org_stock_id', $orgStock->id)->where('location_id', $locationID)->first();
            if ($locationOrgStock) {
                DeleteLocationOrgStock::make()->action($locationOrgStock);
            }
        }

        return $newLocations;
    }

    public function rules(): array
    {
        return [
            'locationsData' => [ 'array'],
        ];
    }


    /**
     * @throws \Throwable
     */
    public function action(OrgStock $orgStock, array $modelData, int $hydratorsDelay = 0, bool $strict = true): array
    {


        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $this->initialisation($orgStock->organisation, $modelData);

        return $this->handle($orgStock, $this->validatedData);
    }


}
