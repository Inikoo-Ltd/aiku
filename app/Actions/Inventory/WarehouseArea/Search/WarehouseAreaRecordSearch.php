<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Aug 2024 21:46:33 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\WarehouseArea\Search;

use App\Models\Inventory\WarehouseArea;
use Lorisleiva\Actions\Concerns\AsAction;

class WarehouseAreaRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(WarehouseArea $warehouseArea): void
    {
        if ($warehouseArea->trashed()) {
            $warehouseArea->universalSearch()->delete();

            return;
        }

        $warehouseArea->universalSearch()->updateOrCreate(
            [],
            [
                'group_id'          => $warehouseArea->group_id,
                'organisation_id'   => $warehouseArea->organisation_id,
                'organisation_slug' => $warehouseArea->organisation->slug,
                'warehouse_id'      => $warehouseArea->warehouse_id,
                'warehouse_slug'    => $warehouseArea->warehouse->slug,
                'sections'          => ['infrastructure'],
                'haystack_tier_1'   => trim($warehouseArea->code.' '.$warehouseArea->name),
                'keyword'           => $warehouseArea->code,
                'result'            => [
                    'route'     => [
                        'name'          => 'grp.org.warehouses.show.infrastructure.warehouse_areas.show',
                        'parameters'    => [
                            $warehouseArea->organisation->slug,
                            $warehouseArea->warehouse->slug,
                            $warehouseArea->slug
                        ]
                    ],
                    'description'      => [
                        'label' => $warehouseArea->name,
                    ],
                    'code' => [
                        'label' => $warehouseArea->code,
                    ],
                    'icon'      => [
                        'icon' => 'fal fa-map-signs',
                    ],
                    'meta'      => [
                        [
                            'type'   => 'number',
                            'label'  => __('Number locations') . ': ',
                            'number' => (int) $warehouseArea->stats->number_locations_status_operational
                        ],

                    ],
                ]
            ]
        );
    }

}
