<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Catalogue;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
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
        $category = null;
        if ($this->item instanceof StoredItem) {
            $quantity = $this->item->total_quantity;
            $itemId = $this->item->id;
            $weight = 0;
            $price = 0;
            $image = null;
        } elseif ($this->item instanceof Product) {
            if ($department = $this->item->department) {
                $department =  $department->name . ', ';
            }

            $quantity = $this->item->available_quantity;
            $itemId = $this->item->current_historic_asset_id;
            $weight = $this->item->gross_weight;
            $price = $this->customer_price ?? $this->item->price;
            $image = $this->item->imageSources(64, 64);
            $category = $department . $this->item->family?->name;
        }

        $shopifyUploadRoute = [];
        $platformProductId = [];
        if ($this->platform->type == PlatformTypeEnum::SHOPIFY) {
            $platformProductId = [
                'platform_product_id' => $this->shopify_product_id
            ];

            $shopifyUploadRoute = [
                'shopify_upload_portfolio' => [
                    'method' => 'post',
                    'name'       => 'retina.models.dropshipping.shopify.single_upload',
                    'parameters' => [
                        'customerSalesChannel' => $this->customerSalesChannel->user->id,
                        'portfolio' => $this->id
                    ]
                ],
            ];
        }

        return [
            'id'                        => $this->id,
            'item_id'                   => $itemId,
            'slug'                      => $this->item?->slug,
            'code'                      => $this->item?->code ?? $this->item_code,
            'currency_code'             => $this->item?->currency?->code,
            'name'                      => $this->customer_product_name ?? $this->item?->name ?? $this->item_name ?? $this->item?->code,
            'description'               => $this->customer_description ?? $this->item?->description ?? $this->item_description,
            'quantity_left'             => $quantity,
            'weight'                    => $weight,
            'price'                     => $price,
            'image'                     => $image,
            'type'                      => $this->item_type,
            'created_at'                => $this->created_at,
            'updated_at'                => $this->updated_at,
            ...$platformProductId,
            'category' => $category,
            'platform' => $this->platform->type,
            'delete_portfolio' => [
                'method' => 'delete',
                'name'       => 'retina.models.customer_sales_channel.product.delete',
                'parameters' => [
                    'customerSalesChannel' => $this->customer_sales_channel_id,
                    'portfolio' => $this->id
                ]
            ],
            ...$shopifyUploadRoute,
        ];
    }
}
