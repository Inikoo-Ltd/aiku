<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Mar 2023 19:55:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\UI;

use App\Http\Resources\Inventory\WarehouseResource;
use App\Models\Fulfilment\Fulfilment;
use Illuminate\Http\Resources\Json\JsonResource;

class FulfilmentsNavigationResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Fulfilment $fulfilment */
        $fulfilment = $this;

        return [
            'id' => $fulfilment->id,
            'slug' => $fulfilment->slug,
            'code' => $fulfilment->shop->code,
            'label' => $fulfilment->shop->name,
            'state' => $fulfilment->shop->state,
            'type' => $fulfilment->shop->type,
            'warehouse' => WarehouseResource::collection($fulfilment->warehouses),
            'route' => [
                'name' => 'grp.org.fulfilments.show.operations.dashboard',
                'parameters' => [
                    $fulfilment->organisation->slug,
                    $fulfilment->slug,
                ],
            ],

        ];
    }
}
