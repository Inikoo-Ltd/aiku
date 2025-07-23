<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Dropshipping;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

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
 * @property mixed $margin
 *
 */
class DropshippingPortfoliosResource extends JsonResource
{
    public function toArray($request): array
    {

        $category = null;
        if ($this->item instanceof StoredItem) {
            $quantity = $this->item->total_quantity;
            $itemId = $this->item->id;
            $weight = 0;
            $price = 0;
            $image = null;
        } else {
            if ($department = $this->item->department) {
                $department =  $department->name . ', ';
            }
            $quantity = $this->item->available_quantity;
            $itemId = $this->item->current_historic_asset_id;
            $weight = $this->item->gross_weight;
            $price = $this->item->price;
            $image = $this->item->imageSources(64, 64);
            $category = $department . $this->item->family?->name;
        }


        $shopifyUploadRoute = [];
        $wooUploadRoute = [];
        $ebayUploadRoute = [];
        $amazonUploadRoute = [];
        $magentoUploadRoute = [];


        if ($this->platform->type != PlatformTypeEnum::MANUAL && $this->customerSalesChannel->user) {
            if ($this->platform->type == PlatformTypeEnum::SHOPIFY) {
                $shopifyUploadRoute = [
                    'platform_upload_portfolio' => [
                        'method' => 'post',
                        'name'       => 'retina.models.dropshipping.shopify.single_upload',
                        'parameters' => [
                            'shopifyUser' => $this->customerSalesChannel->user->id,
                            'portfolio' => $this->id
                        ]
                    ],
                ];
            }

            if ($this->platform->type == PlatformTypeEnum::WOOCOMMERCE) {
                $wooUploadRoute = [
                    'platform_upload_portfolio' => [
                        'method' => 'post',
                        'name'       => 'retina.models.dropshipping.woo.single_upload',
                        'parameters' => [
                            'wooCommerceUser' => $this->customerSalesChannel->user->id,
                            'portfolio' => $this->id
                        ]
                    ],
                ];
            }

            if ($this->platform->type == PlatformTypeEnum::EBAY) {
                $ebayUploadRoute = [
                    'platform_upload_portfolio' => [
                        'method' => 'post',
                        'name'       => 'retina.models.dropshipping.ebay.single_upload',
                        'parameters' => [
                            'ebayUser' => $this->customerSalesChannel->user->id,
                            'portfolio' => $this->id
                        ]
                    ],
                ];
            }

            if ($this->platform->type == PlatformTypeEnum::AMAZON) {
                $amazonUploadRoute = [
                    'platform_upload_portfolio' => [
                        'method' => 'post',
                        'name'       => 'retina.models.dropshipping.amazon.single_upload',
                        'parameters' => [
                            'amazonUser' => $this->customerSalesChannel->user->id,
                            'portfolio' => $this->id
                        ]
                    ],
                ];
            }

            if ($this->platform->type == PlatformTypeEnum::MAGENTO) {
                $magentoUploadRoute = [
                    'platform_upload_portfolio' => [
                        'method' => 'post',
                        'name'       => 'retina.models.dropshipping.magento.single_upload',
                        'parameters' => [
                            'magentoUser' => $this->customerSalesChannel->user->id,
                            'portfolio' => $this->id
                        ]
                    ],
                ];
            }
        }




        return [
            'id'                        => $this->id,
            'item_id'                   => $itemId,
            'code'                      => $this->item?->code ?? $this->item_code,
            'currency_code'             => $this->item?->currency?->code,
            'handle'                    => $this->platform_handle,
            'name'                      => $this->customer_product_name ?? $this->item?->name ?? $this->item_name ?? $this->item?->code,
            'description'               => $this->customer_description ?? $this->item?->description ?? $this->item_description,
            'quantity_left'             => $quantity,
            'weight'                    => $weight,
            'price'                     => $price,
            'selling_price'             => $this->selling_price,
            'customer_price'            => $this->customer_price,
            'status'                    => $this->status,
            'margin'                    => percentage($this->margin, 1),
            'image'                     => $image,
            'type'                      => $this->item_type,
            'created_at'                => $this->created_at,
            'updated_at'                => $this->updated_at,
            'platform_product_id'       => $this->platform_product_id,
            'upload_warning'            => $this->upload_warning,
            'is_code_exist_in_platform' =>  false, // ! blank($this->platform_product_availabilities), (we will use later when its ready)
            'product_availability'      => [
                'options' => Arr::get($this->platform_product_availabilities, 'options'),
                'name' => Arr::get($this->platform_product_availabilities, 'title'),
                'handle' => Arr::get($this->platform_product_availabilities, 'handle'),
                'sku' => Arr::get($this->platform_product_availabilities, 'variants.0.sku'),
                'barcode' => Arr::get($this->platform_product_availabilities, 'variants.0.barcode'),
            ],
            'category' => $category,
            'platform' => $this->platform->type,
            'delete_portfolio' => [
                'method' => 'delete',
                'name'       => 'retina.models.portfolio.delete',
                'parameters' => [
                    'portfolio' => $this->id
                ]
            ],
            'update_portfolio' => [
                'method' => 'patch',
                'name'       => 'retina.models.portfolio.update',
                'parameters' => [
                    'portfolio' => $this->id
                ]
            ],
            ...$shopifyUploadRoute,
            ...$wooUploadRoute,
            ...$ebayUploadRoute,
            ...$amazonUploadRoute,
            ...$magentoUploadRoute
        ];
    }
}
