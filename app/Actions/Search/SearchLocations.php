<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 09 Jul 2026 11:03:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Inventory\Location;
use App\Models\Inventory\WarehouseArea;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchLocations
{
    use AsAction;
    use WithRawSearchResults;

    public function handle(string $query, array $options): array
    {
        $organisationId = Arr::get($options, 'organisation_id');
        $locationsQuery = Location::search($query);
        if ($organisationId) {
            $locationsQuery->where('organisation_id', $organisationId);
        }

        $warehouseAreasQuery = WarehouseArea::search($query);
        if ($organisationId) {
            $warehouseAreasQuery->where('organisation_id', $organisationId);
        }

        return [
            'scope'   => 'locations',
            'results' => [
                'locations'       => array_map(static fn (array $document) => [
                    'id'     => (int)$document['id'],
                    'slug'   => $document['slug'] ?? null,
                    'code'   => $document['code'] ?? null,
                    'status' => $document['status'] ?? null,
                ], $this->rawDocuments($locationsQuery)),
                'warehouse_areas' => array_map(static fn (array $document) => [
                    'id'   => (int)$document['id'],
                    'slug' => $document['slug'] ?? null,
                    'code' => $document['code'] ?? null,
                    'name' => $document['name'] ?? null,
                ], $this->rawDocuments($warehouseAreasQuery)),
            ],
        ];
    }


}
