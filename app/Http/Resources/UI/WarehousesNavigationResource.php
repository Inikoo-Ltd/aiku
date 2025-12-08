<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Jun 2023 15:24:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\UI;

use App\Models\Inventory\Warehouse;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehousesNavigationResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Warehouse $warehouse */
        $warehouse = $this;

        return [
            'id' => $warehouse->id,
            'slug' => $warehouse->slug,
            'code' => $warehouse->code,
            'label' => $warehouse->name,
            'route' => [
                'name' => 'grp.org.warehouses.show.infrastructure.dashboard',
                'parameters' => [
                    $warehouse->organisation->slug,
                    $warehouse->slug,
                ],
            ],
        ];
    }
}
