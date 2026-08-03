<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 25 May 2023 15:03:06 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Inventory\Warehouse\UI;

use App\Http\Resources\Helpers\AddressResource;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWarehouseShowcase
{
    use AsObject;

    public function handle(Warehouse $warehouse, $routeParameters): array
    {
        return [
            'address'   => $warehouse->address ? AddressResource::make($warehouse->address) : null,
            'box_stats' => [
                [
                    'name'  => trans_choice('warehouse area|warehouse areas', $warehouse->stats->number_warehouse_areas),
                    'value' => $warehouse->stats->number_warehouse_areas,
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.infrastructure.warehouse_areas.index',
                        'parameters' => array_merge($routeParameters, [$warehouse->slug])
                    ],
                    'icon'  => [
                        'icon'    => 'fal fa-map-signs',
                        'tooltip' => __('warehouse areas')
                    ]
                ],
                [
                    'name'  => trans_choice('location|locations', $warehouse->stats->number_locations),
                    'value' => $warehouse->stats->number_locations,
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.infrastructure.locations.index',
                        'parameters' => array_merge($routeParameters, [$warehouse->slug])
                    ],
                    'icon'  => [
                        'icon'    => 'fal fa-inventory',
                        'tooltip' => __('Locations')
                    ]
                ],
                [
                    'name'  => __('Empty locations'),
                    'value' => $warehouse->stats->number_locations_stock_slots_all_empty,
                    'color' => 'red',
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.infrastructure.locations.all_empty',
                        'parameters' => array_merge($routeParameters, [$warehouse->slug])
                    ],
                    'icon'  => [
                        'icon'    => 'fal fa-box-open',
                        'tooltip' => __('Locations with all stock slots empty')
                    ]
                ],
                [
                    'name'  => __('Partially empty locations'),
                    'value' => $warehouse->stats->number_locations_stock_slots_partial_empty,
                    'color' => 'orange',
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.infrastructure.locations.partial_empty',
                        'parameters' => array_merge($routeParameters, [$warehouse->slug])
                    ],
                    'icon'  => [
                        'icon'    => 'fal fa-box',
                        'tooltip' => __('Locations with some stock slots empty')
                    ]
                ],
            ],

        ];
    }
}
