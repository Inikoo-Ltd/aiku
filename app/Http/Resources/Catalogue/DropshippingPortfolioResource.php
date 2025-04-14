<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Catalogue;

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
class DropshippingPortfolioResource extends JsonResource
{
    public function toArray($request): array
    {
        $quantity = 0;
        $itemId   = null;
        if ($this->item instanceof StoredItem) {
            $quantity = $this->item->total_quantity;
            $itemId = $this->item->id;
        } elseif ($this->item instanceof Product) {
            $quantity = $this->item->available_quantity;
            $itemId = $this->item->current_historic_asset_id;
        }

        return [
            'id'                        => $this->id,
            'item_id'                   => $itemId,
            'slug'                      => $this->item->slug,
            'code'                      => $this->item->code,
            'name'                      => $this->item->name ?? $this->item->code,
            'quantity_left'             => $quantity,
            'type'                      => $this->item_type,
            'created_at'                => $this->created_at,
            'updated_at'                => $this->updated_at,
            'delete_product' => [
                'method' => 'delete',
                'name'       => 'retina.models.dropshipping.shopify_user.product.delete',
                'parameters' => [
                    'product' => $this->id
                ]
            ],
        ];
    }
}
