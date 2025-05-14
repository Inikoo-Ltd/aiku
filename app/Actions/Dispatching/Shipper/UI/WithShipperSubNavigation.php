<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Dispatching\Shipper\UI;

use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;

trait WithShipperSubNavigation
{
    protected function getShipperNavigation(Organisation $organisation, Warehouse $warehouse): array
    {
        return [
            [
                "label"    => __("Current"),
                "isAnchor" => true,
                "route"     => [
                    "name"       => 'grp.org.warehouses.show.dispatching.shippers.current.index',
                    "parameters" => [
                        'organisation' => $organisation->slug,
                        'warehouse'         => $warehouse->slug
                    ],
                ],
            ],
            [
                "label"    => __("Inactive"),
                "route"     => [
                    "name"       => 'grp.org.warehouses.show.dispatching.shippers.inactive.index',
                    "parameters" => [
                        'organisation' => $organisation->slug,
                        'warehouse'         => $warehouse->slug
                    ],
                ],
                'align'  => 'right',
            ],

        ];
    }
}
