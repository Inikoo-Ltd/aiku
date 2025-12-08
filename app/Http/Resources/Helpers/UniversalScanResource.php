<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Helpers;

use App\Http\Resources\Fulfilment\PalletDeliveryResource;
use App\Http\Resources\Fulfilment\PalletResource;
use App\Http\Resources\Fulfilment\PalletReturnResource;
use App\Http\Resources\Fulfilment\StoredItemResource;
use App\Http\Resources\Inventory\LocationResource;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $model_type
 */
class UniversalScanResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'model_type' => $this->model_type,
            'model' => $this->when(true, function () {
                return match ($this->model_type) {
                    'Location' => new LocationResource($this->resource->model),
                    'Pallet' => new PalletResource($this->resource->model),
                    'Item' => new StoredItemResource($this->resource->model),
                    'PalletDelivery' => new PalletDeliveryResource($this->resource->model),
                    'PalletReturn' => new PalletReturnResource($this->resource->model),
                    default => [],
                };
            }),
        ];
    }
}
