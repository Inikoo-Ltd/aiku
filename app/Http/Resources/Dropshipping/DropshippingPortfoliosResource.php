<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Dropshipping;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Helpers\NaturalLanguage;
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
 * @property mixed $platform_product_id
 * @property mixed $item_description
 * @property mixed $id
 * @property mixed $is_bundle
 * @property mixed $selling_price
 * @property mixed $platform_handle
 * @property mixed $item_id
 * @property mixed $bundle_id
 * @property mixed $is_for_sale
 * @property mixed $rrp
 * @property mixed $platform_type
 * @property mixed $available_quantity
 * @property mixed $current_historic_asset_id
 * @property mixed $gross_weight
 * @property mixed $marketing_weight
 * @property mixed $marketing_dimensions
 * @property mixed $price
 * @property mixed $web_images
 * @property mixed $customer_price
 * @property mixed $status
 * @property mixed $upload_warning
 * @property mixed $platform_status
 * @property mixed $data
 * @property mixed $platform_user_id
 * @property mixed $item_code
 * @property mixed $currency_code
 * @property mixed $item_type
 * @property mixed $errors_response
 * @property mixed $has_valid_platform_product_id
 * @property mixed $exist_in_platform
 * @property mixed $platform_possible_matches
 * @property mixed $customer_sales_channels_platform_status
 * @property mixed $product_code
 *
 */
class DropshippingPortfoliosResource extends JsonResource
{
    public function toArray($request): array
    {
        //        if ($department = $this->item?->department) {
        //            $department = $department->name.', ';
        //        }
        $quantity         = $this->available_quantity;
        $itemId           = $this->current_historic_asset_id;
        $weight           = $this->gross_weight;
        $marketing_weight = $this->marketing_weight;
        $dimension        = NaturalLanguage::make()->dimensions($this->marketing_dimensions);
        $price            = $this->price;
        $image            = Arr::get($this->web_images, 'main.thumbnail');
        $fullSizeImage    = Arr::get($this->web_images, 'main.gallery');
        // $category         = $department.$this->item->family?->name;


        $shopifyUploadRoute = [];
        $wooUploadRoute     = [];
        $ebayUploadRoute    = [];
        $amazonUploadRoute  = [];
        $magentoUploadRoute = [];


        if ($this->platform_type != PlatformTypeEnum::MANUAL->value && $this->platform_user_id) {
            if ($this->platform_type == PlatformTypeEnum::SHOPIFY->value) {
                $shopifyUploadRoute = [
                    'platform_upload_portfolio' => [
                        'method'     => 'post',
                        'name'       => 'retina.models.dropshipping.shopify.single_upload',
                        'parameters' => [
                            'shopifyUser' => $this->platform_user_id,
                            'portfolio'   => $this->id
                        ]
                    ],
                ];
            }

            if ($this->platform_type == PlatformTypeEnum::WOOCOMMERCE->value) {
                $wooUploadRoute = [
                    'platform_upload_portfolio' => [
                        'method'     => 'post',
                        'name'       => 'retina.models.dropshipping.woo.single_upload',
                        'parameters' => [
                            'wooCommerceUser' => $this->platform_user_id,
                            'portfolio'       => $this->id
                        ]
                    ],
                ];
            }

            if ($this->platform_type == PlatformTypeEnum::EBAY->value) {
                $ebayUploadRoute = [
                    'platform_upload_portfolio' => [
                        'method'     => 'post',
                        'name'       => 'retina.models.dropshipping.ebay.single_upload',
                        'parameters' => [
                            'ebayUser'  => $this->platform_user_id,
                            'portfolio' => $this->id
                        ]
                    ],
                ];
            }

            if ($this->platform_type == PlatformTypeEnum::AMAZON->value) {
                $amazonUploadRoute = [
                    'platform_upload_portfolio' => [
                        'method'     => 'post',
                        'name'       => 'retina.models.dropshipping.amazon.single_upload',
                        'parameters' => [
                            'amazonUser' => $this->platform_user_id,
                            'portfolio'  => $this->id
                        ]
                    ],
                ];
            }

            if ($this->platform_type == PlatformTypeEnum::MAGENTO->value) {
                $magentoUploadRoute = [
                    'platform_upload_portfolio' => [
                        'method'     => 'post',
                        'name'       => 'retina.models.dropshipping.magento.single_upload',
                        'parameters' => [
                            'magentoUser' => $this->platform_user_id,
                            'portfolio'   => $this->id
                        ]
                    ],
                ];
            }
        }

        return [
            'id'                    => $this->id,
            'item_id'               => $itemId,
            'product_id'            => $this->item_id,
            'code'                  => $this->product_code ?? $this->item_code,
            'currency_code'         => $this->currency_code,
            'handle'                => $this->platform_handle,
            'name'                  => $this->customer_product_name ?? $this->product_name ?? $this->item_name ?? $this->product_code,
            'description'           => $this->customer_description ?? $this->product_description ?? $this->item_description,
            'quantity_left'         => $quantity,
            'weight'                => $weight,
            'marketing_weight'      => $marketing_weight,
            'dimension'             => $dimension,
            'price'                 => $price,
            'price_include_vat'     => $price,
            'selling_price'         => $this->selling_price,
            'customer_price'        => $this->customer_price,
            'status'                => $this->status,
            'margin'                => percentage($this->margin, 1),
            'image'                 => $image,
            'full_size_image'       => $fullSizeImage,
            'type'                  => $this->item_type,
            'created_at'            => $this->created_at,
            'updated_at'            => $this->updated_at,
            'platform_product_id'   => $this->platform_product_id,
            'upload_warning'        => $this->upload_warning,
            'message'               => $this->platform_status ? 'OK' : Arr::get($this->errors_response, 'message', ''),
            'shopify_product_data'  => Arr::get($this->data, 'shopify_product', []),
            'platform_product_data' => match ($this->platform_type) {
                PlatformTypeEnum::WOOCOMMERCE->value => Arr::get($this->data, 'woo_product', []),
                PlatformTypeEnum::EBAY->value => Arr::get($this->data, 'ebay_product', []),
                default => [],
            },

            'portfolio_data' => $this->data,
            'bundle_id'      => $this->bundle_id,
            'is_bundle'      => $this->is_bundle,

            'has_valid_platform_product_id'          => $this->has_valid_platform_product_id,
            'exist_in_platform'                      => $this->exist_in_platform,
            'platform_status'                        => $this->platform_status,
            'platform_possible_matches'              => $this->platform_possible_matches,
            'customer_sales_channel_platform_status' => $this->customer_sales_channels_platform_status,

            //'category'         => $category,
            'platform'                               => $this->platform_type,
            'delete_portfolio'                       => [
                'method'     => 'delete',
                'name'       => 'retina.models.portfolio.delete',
                'parameters' => [
                    'portfolio' => $this->id
                ]
            ],
            'unlink_portfolio'                       => [
                'method'     => 'delete',
                'name'       => 'retina.models.portfolio.unlink',
                'parameters' => [
                    'portfolio' => $this->id
                ]
            ],
            'update_portfolio'                       => [
                'method'     => 'patch',
                'name'       => 'retina.models.portfolio.update',
                'parameters' => [
                    'portfolio' => $this->id
                ]
            ],
            'product_state'                          => $this->product_state ?? null,
            'is_for_sale'                            => ($this->is_bundle ? true : $this->is_for_sale) ?? null,
            'product_rrp'                            => $this->rrp,
            ...$shopifyUploadRoute,
            ...$wooUploadRoute,
            ...$ebayUploadRoute,
            ...$amazonUploadRoute,
            ...$magentoUploadRoute
        ];
    }
}
