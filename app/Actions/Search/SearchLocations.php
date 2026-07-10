<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 09 Jul 2026 11:03:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Http\Resources\Inventory\LocationsSearchResultResource;
use App\Http\Resources\Inventory\WarehouseAreasSearchResultResource;
use App\Models\Inventory\Location;
use App\Models\Inventory\WarehouseArea;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchLocations
{
    use AsAction;

    public function handle(string $query, array $options): array
    {
        $organisationId = Arr::get($options, 'organisation_id');
        $locations      = Location::search($query);
        if ($organisationId) {
            $locations->where('organisation_id', $organisationId);
        }

        $warehouseAreas = WarehouseArea::search($query);
        if ($organisationId) {
            $warehouseAreas->where('organisation_id', $organisationId);
        }


        return [
            'scope'   => 'locations',
            'results' => [
                'locations'       => LocationsSearchResultResource::collection($locations->get()),
                'warehouse_areas' => WarehouseAreasSearchResultResource::collection($warehouseAreas->get()),

            ],
        ];
    }


}
