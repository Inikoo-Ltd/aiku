<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 *
 * @property mixed $id
 * @property mixed $item_id
 * @property mixed $product_slug
 * @property mixed $product_code
 * @property mixed $currency_code
 * @property mixed $product_name
 * @property mixed $available_quantity
 * @property mixed $item_type
 * @property mixed $barcode
 * @property mixed $gross_weight
 * @property mixed $price
 * @property mixed $web_images
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $product_state
 * @property mixed $is_for_sale
 */
class DropshippingApiPortfoliosResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'item_id'       => $this->item_id,
            'slug'          => $this->product_slug,
            'code'          => $this->product_code,
            'currency_code' => $this->currency_code,
            'name'          => $this->product_name,
            'quantity_left' => $this->available_quantity,
            'weight'        => $this->gross_weight,
            'price'         => $this->price,
            'image'         => Arr::get(json_decode($this->web_images, true), 'main.gallery'),
            'type'          => $this->item_type,
            'ean_barcode'   => $this->barcode,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
            'product_state' => $this->product_state,
            'is_for_sale'   => $this->is_for_sale,
        ];
    }
}
