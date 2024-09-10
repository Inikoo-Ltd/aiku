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
                'sections'          => ['inventory'],
                'haystack_tier_1'   => $location->code,
                'keyword'           => $location->barcode,
                'keyword_2'         => $location->code,
                'result'            => [
                    'route'     => [
                        'name'          => 'grp.org.warehouses.show.infrastructure.locations.show',
                        'parameters'    => [
                            $location->organisation->slug,
                            $location->warehouse->slug,
                            $location->slug,
                        ]
                    ],
                    'container' => [
                        'label' => $location->warehouse->name,
                    ],
                    'title'        => $location->code,
                    // 'afterTitle'   => [
                    //     'label'     => '(#' . $location->reference . ')',
                    //     'tooltip'   => __('reference')
                    // ],
                    'icon'      => [
                        'icon' => 'fal fa-inventory',
                    ],
                    'meta'      => [
                        [
                            'key'       => 'status',
                            'label'     => $location->status,
                            'tooltip'   => __('Tooltip')
                        ],
                        [
                            'key'       => 'created_date',
                            'type'      => 'date',
                            'label'     => $location->created_at,
                            'tooltip'   => __('Created at')
                        ],
                        [
                            'key'        => 'stock_value',
                            'type'       => 'number',
                            'number'     => $location->stock_value,
                            'tooltip'    => __('Created at')
                        ],
                        // [
                        //     'key'       => 'address',
                        //     'type'      => 'address',
                        //     'label'     => $location->location,
                        //     'tooltip'   => __('Location')
                        // ],
                        // [
                        //     'key'   => 'contact_name',
                        //     // 'type'  => 'address',
                        //     'label'     => $location->contact_name,
                        //     'tooltip'   => __('Contact name')
                        // ],
                        // [
                        //     'key'   => 'email',
                        //     // 'type'  => 'address',
                        //     'label'     => $location->email,
                        //     'tooltip'   => __('Email')
                        // ],
                    ],
                ]
            ]
        );
    }

}
