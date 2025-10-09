<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Aug 2024 16:04:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Location\Search;

use App\Models\Inventory\Location;
use Lorisleiva\Actions\Concerns\AsAction;

class LocationRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(Location $location): void
    {
        if ($location->trashed()) {
            $location->universalSearch()->delete();

            return;
        }

        $location->universalSearch()->updateOrCreate(
            [],
            [
                'group_id'          => $location->group_id,
                'organisation_id'   => $location->organisation_id,
                'organisation_slug' => $location->organisation->slug,
                'warehouse_id'      => $location->warehouse_id,
                'warehouse_slug'    => $location->warehouse->slug,
                'sections'          => ['infrastructure'],
                'haystack_tier_1'   => $location->code,
                'keyword'           => $location->barcode,
                'result'            => [
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.infrastructure.locations.show',
                        'parameters' => [
                            $location->organisation->slug,
                            $location->warehouse->slug,
                            $location->slug,
                        ]
                    ],
                    'code' => [
                        'label' => $location->code
                    ],
                    'icon' => [
                        'icon' => 'fal fa-inventory',
                    ],
                    'meta' => [
                        [
                            'type'    => 'number',
                            'number'  => $location->stats->number_org_stock_slots,
                            'tooltip' => __('Sku Slots'),
                            'warning' => $location->stats->number_empty_stock_slots > 0 ? [
                                'tooltip' => __('Empty Slots'),
                                'number'  => $location->stats->number_empty_stock_slots,
                            ] : null,

                        ],
                        $location->stock_value ? [
                            'type'    => 'number',
                            'number'  => $location->stock_value,
                            'tooltip' => __('Stock value')
                        ] : null,
                    ],
                ]
            ]
        );
    }

}
