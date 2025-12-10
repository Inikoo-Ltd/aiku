<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 15 Sept 2022 14:55:27 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use App\Models\Inventory\Warehouse;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Warehouse $warehouse */
        $warehouse = $this;

        return [
            'id' => $warehouse->id,
            'slug' => $warehouse->slug,
            'name' => $warehouse->name,
            'stats' => [
                'locations' => [
                    'label' => 'Location',
                    'count' => $warehouse->locations()->count(),
                ],

                'pallets' => [
                    'label' => 'Pallets',
                    'count' => $warehouse->pallets()->count(),
                ],

                'stored_items' => [
                    'label' => 'Stored Items',
                    'count' => 0,
                ],

                'deliveries' => [
                    'label' => 'Delivery',
                    'count' => $warehouse->palletDeliveries()->count(),
                ],

                'returns' => [
                    'label' => 'Return',
                    'count' => $warehouse->palletReturns()->count(),
                ],
            ],
        ];
    }
}
