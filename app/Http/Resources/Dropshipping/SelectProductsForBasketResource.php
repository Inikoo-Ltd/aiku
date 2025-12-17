<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 08 Jun 2025 20:10:14 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dropshipping;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

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
 * @property mixed $group_id
 * @property mixed $organisation_id
 * @property mixed $shop_id
 * @property mixed $webpage_id
 * @property mixed $website_id
 * @property mixed $web_images
 */
class SelectProductsForBasketResource extends JsonResource
{
    public function toArray($request): array
    {
        $oldLuigiIdentity = $this->group_id.':'.$this->organisation_id.':'.$this->shop_id.':'.$this->website_id.':'.$this->webpage_id;

        return [
            'id'                 => $this->id,
            'slug'               => $this->slug,
            'code'               => $this->code,
            'name'               => $this->name,
            'image'              => Arr::get($this->web_images, 'main.gallery'),
            'luigi_identity'     => $oldLuigiIdentity,
            'price'              => $this->price,
            'available_quantity' => $this->available_quantity,
            'transaction_id'     => $this->transaction_id,
            'historic_asset_id'  => $this->historic_asset_id,
            'quantity_ordered'   => (int)$this->quantity_ordered,
        ];
    }
}
