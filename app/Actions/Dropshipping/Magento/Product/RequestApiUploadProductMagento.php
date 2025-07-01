<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Magento\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\RetinaAction;
use App\Events\UploadProductToMagentoProgressEvent;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\MagentoUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class RequestApiUploadProductMagento extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Exception
     */
    public function handle(MagentoUser $magentoUser, Portfolio $portfolio)
    {
        try {
            /** @var Product $product */
            $product = $portfolio->item;

            $images = [];
            if (app()->isProduction()) {
                foreach ($product->images as $key => $image) {
                    $images[] = [
                        'media_type' => 'image',
                        'label' => 'Product Image 1',
                        'position' => $key + 1,
                        'disabled' => false,
                        'types' => $key === 0 ? ['image', 'small_image', 'thumbnail'] : ['image'],
                        'content' => [
                            'base64_encoded_data' => base64_encode(file_get_contents(GetImgProxyUrl::run($image->getImage()->extension('png')))),
                            'type' => 'image/png',
                            'name' => $image->file_name
                        ]
                    ];
                }
            }

            $wooCommerceProduct = [
                'sku' => $portfolio->item_code,
                'name' => $portfolio->customer_product_name,
                'attribute_set_id' => $productData['attribute_set_id'] ?? 4,
                'price' => $portfolio->selling_price,
                'status' => 1, // 1 = enabled
                'visibility' => 4, // 4 = catalog & search
                'type_id' => 'simple',
                'weight' => $product->gross_weight / 453.59237, // Change to lbs
                'extension_attributes' => [
                    'stock_item' => [
                        'stock_id' => 1,
                        'is_in_stock' => 1,
                        'qty' => $product->available_quantity,
                        'manage_stock' => 0,
                        'use_config_manage_stock' => 0,
                        'min_qty' => 0,
                        'use_config_min_qty' => 1,
                        'min_sale_qty' => 1,
                        'use_config_min_sale_qty' => 1,
                        'max_sale_qty' => 10000,
                        'use_config_max_sale_qty' => 1,
                        'is_qty_decimal' => 0,
                        'backorders' => 0,
                        'use_config_backorders' => 1,
                        'notify_stock_qty' => 1,
                        'use_config_notify_stock_qty' => 1
                    ]
                ],
                'media_gallery_entries' => $images
            ];

            $result = $magentoUser->uploadProduct($wooCommerceProduct);

            $portfolio = UpdatePortfolio::run($portfolio, [
                'platform_product_id' => Arr::get($result, 'id')
            ]);

            UploadProductToMagentoProgressEvent::dispatch($magentoUser, $portfolio);
        } catch (\Exception $e) {
            Sentry::captureMessage("Failed to upload product due to: " . $e->getMessage());
        }
    }
}
