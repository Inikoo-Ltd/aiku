<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 08 Jun 2025 20:10:14 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dropshipping;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $code
 * @property mixed $name
 * @property mixed $price
 * @property mixed $available_quantity
 * @property mixed $transaction_id
 * @property mixed $quantity_ordered
 * @property mixed $historic_asset_id
 */
class SelectProductsForBasketResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'slug'               => $this->slug,
            'code'               => $this->code,
            'name'               => $this->name,
            'price'              => $this->price,
            'available_quantity' => $this->available_quantity,
            'transaction_id'     => $this->transaction_id,
            'historic_asset_id'  => $this->historic_asset_id,
            'quantity_ordered'   => $this->quantity_ordered,
        ];
    }
}
