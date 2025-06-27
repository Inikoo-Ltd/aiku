<?php


/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Fulfilment\Resource;

use App\Models\Catalogue\Product;
use App\Models\Fulfilment\StoredItem;
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
 * @property StoredItem|Product $item
 *
 */
class PortfolioApiResource extends JsonResource
{
    public function toArray($request): array
    {
        $quantity = 0;
        $itemId   = null;
        if ($this->item instanceof StoredItem) {
            $quantity = $this->item->total_quantity;
            $itemId = $this->item->id;
            $weight = 0;
            $price = 0;
            $image = null;
        } elseif ($this->item instanceof Product) {
            $quantity = $this->item->available_quantity;
            $itemId = $this->item->current_historic_asset_id;
            $weight = $this->item->gross_weight;
            $price = $this->item->price;
            $image = $this->item->imageSources(64, 64);
        }

        return [
            'id'                        => $this->id,
            'item_id'                   => $itemId,
            'slug'                      => $this->item?->slug,
            'code'                      => $this->item?->code ?? $this->item_code,
            'currency_code'             => $this->item?->currency?->code,
            'name'                      => $this->item?->name ?? $this->item_name ?? $this->item?->code,
            'customer_product_name'     => $this->customer_product_name,
            'customer_description'      => $this->customer_description,
            'selling_price'             => $this->selling_price,
            'quantity_left'             => $quantity,
            'weight'                    => $weight,
            'price'                     => $price,
            'image'                     => $image,
            'type'                      => $this->item_type,
            'created_at'                => $this->created_at,
            'updated_at'                => $this->updated_at,
        ];
    }
}
