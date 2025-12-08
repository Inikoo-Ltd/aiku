<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:30:19 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $data
 * @property float $unit_quantity
 * @property float $unit_price
 * @property string $created_at
 * @property string $updated_at
 */
class StockDeliveryItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'unit_quantity' => $this->unit_quantity,
            'unit_price' => $this->unit_price,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
