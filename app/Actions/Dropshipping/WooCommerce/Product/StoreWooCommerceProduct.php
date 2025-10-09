<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\RetinaAction;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class StoreWooCommerceProduct extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Exception
     */
    public function handle(WooCommerceUser $wooCommerceUser, Portfolio $portfolio)
    {
        try {
            /** @var Product $product */
            $product = $portfolio->item;

            $images = [];
            if (app()->isProduction()) {
                foreach ($product->images as $image) {
                    $images[] = [
                        'src' => GetImgProxyUrl::run($image->getImage()->extension('jpg'))
                    ];
                }
            }


            $wooCommerceProduct = [
                'name' => $portfolio->customer_product_name,
                'type' => 'simple',
                'regular_price' => (string)$portfolio->customer_price,
                'description' => $portfolio->customer_description,
                'short_description' => $portfolio->customer_description,
                // 'categories' => $portfolio->item->family?->name,
                'categories' => [],
                'images' => $images,
                'stock_quantity' => $product->available_quantity,
                'manage_stock' => !is_null($product->available_quantity),
                'stock_status' => Arr::get($product, 'stock_status', 'instock'),
                'attributes' => Arr::get($product, 'attributes', []),
                'sku' => $portfolio->sku,
                'weight' => (string)$product->gross_weight,
                'status' => $this->mapProductStateToWooCommerce($product->status->value)
            ];

            $result = $wooCommerceUser->createWooCommerceProduct($wooCommerceProduct);

            if (Arr::get($result, 'code') === 'product_invalid_sku') {
                $wooCommerceProduct['sku'] = Arr::get($result, 'data.unique_sku') . '-' . rand(0, 99);

                $result = $wooCommerceUser->createWooCommerceProduct($wooCommerceProduct);
            }

            UpdatePortfolio::run($portfolio, [
                'platform_product_id' => Arr::get($result, 'id'),
                'platform_product_variant_id' => Arr::get($result, 'id'),
            ]);

            CheckWooPortfolio::run($portfolio, []);

            return $result;
        } catch (\Exception $e) {
            Sentry::captureMessage("Failed to upload product due to: " . $e->getMessage());

            UpdatePortfolio::run($portfolio, [
                'errors_response' => [$e->getMessage()]
            ]);

            return null;
        }
    }

    private function mapProductStateToWooCommerce($status): string
    {
        $stateMap = [
            ProductStatusEnum::FOR_SALE->value => 'publish',
            ProductStatusEnum::DISCONTINUED->value => 'pending',
            ProductStatusEnum::IN_PROCESS->value => 'draft',
            ProductStatusEnum::OUT_OF_STOCK->value => 'draft'
        ];

        return $stateMap[$status] ?? 'draft';
    }
}
