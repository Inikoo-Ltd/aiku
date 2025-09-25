<?php

/*
 * author Arya Permana - Kirin
 * created on 06-12-2024-11h-09m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Catalogue;

use App\Models\Catalogue\Product;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 * @property mixed $state
 * @property string $shop_slug
 * @property mixed $shop_code
 * @property mixed $shop_name
 * @property mixed $department_slug
 * @property mixed $department_code
 * @property mixed $department_name
 * @property mixed $family_slug
 * @property mixed $family_code
 * @property mixed $family_name
 * @property mixed $id
 * @property mixed $asset_id
 * @property mixed $current_historic_asset_id
 * @property mixed $available_quantity
 * @property mixed $quantity_ordered
 * @property mixed $transaction_id
 * @property mixed $order_id
 */
class OrderProductsForModifyResource extends JsonResource
{
    public function toArray($request): array
    {
        $product = Product::find($this->id)->first();
        return [
            'id'                        => $this->id,
            'slug'                      => $this->slug,
            'asset_id'                  => $this->asset_id,
            'historic_id'               => $this->current_historic_asset_id,
            'code'                      => $this->code,
            'name'                      => $this->name,
            'image_thumbnail'           => $product->imageSources(64, 64),
            'state'                     => $this->state,
            'available_quantity'        => $this->available_quantity,
            'price'                     => $this->price,
        ];
    }
}
