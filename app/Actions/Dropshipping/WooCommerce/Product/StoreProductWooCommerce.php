<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\RetinaAction;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Platform;
use App\Models\Fulfilment\StoredItem;
use App\Models\WooCommerceUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreProductWooCommerce extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    public $commandSignature = 'dropshipping:wooCommerce:product:batchUpload';

    /**
     * @throws \Exception
     */
    public function handle(WooCommerceUser $wooCommerceUser, array $products = [])
    {
        if (empty($products)) {
            throw new \Exception('No product IDs provided for batch upload');
        }

        $successCount = 0;
        $failedProducts = [];
        $platform = Platform::where('type', PlatformTypeEnum::WOOCOMMERCE->value)->first();

        foreach (Arr::get($products, 'items') as $productId) {
            try {
                // Find the product by ID
                $product = $this->findProductById($productId);

                if (!$product) {
                    throw new \Exception("Product with ID {$productId} not found");
                }

                // Validate required fields
                if (empty(Arr::get($product, 'name')) || empty(Arr::get($product, 'price'))) {
                    throw new \Exception('Product name and regular price are required');
                }

                // Create product data array for WooCommerce API
                $wooCommerceProduct = [
                    'name' => Arr::get($product, 'name'),
                    'type' => Arr::get($product, 'type', 'simple'),
                    'regular_price' => (string) Arr::get($product, 'price'),
                    'description' => Arr::get($product, 'description', ''),
                    'short_description' => Arr::get($product, 'short_description', ''),
                    'categories' => Arr::get($product, 'categories', []),
                    'images' => [],
                    'stock_quantity' => Arr::get($product, 'quantity_available'),
                    'manage_stock' => !is_null(Arr::get($product, 'quantity_available')),
                    'stock_status' => Arr::get($product, 'stock_status', 'instock'),
                    'attributes' => Arr::get($product, 'attributes', []),
                    'status' => $this->mapProductStateToWooCommerce($product->status->value)
                ];

                $result = $wooCommerceUser->createWooCommerceProduct($wooCommerceProduct);

                $portfolio = StorePortfolio::make()->action($wooCommerceUser->customer, $product, [
                    'platform_id' => $platform->id,
                ]);

                $wooCommerceUser->products()->attach($product->id, [
                    'woo_commerce_user_id' => $wooCommerceUser->id,
//                    'product_type' => class_basename($product),
                    'product_id' => $product->id,
                    'portfolio_id' => $portfolio->id,
                    'woo_commerce_product_id' => Arr::get($result, 'id')
                ]);

                $successCount++;
            } catch (\Exception $e) {
                $failedProducts[] = [
                    'id' => $productId,
                    'error' => $e->getMessage()
                ];

                Log::info(json_encode($failedProducts));
            }
        }

        return [
            'success' => $successCount,
            'failed' => count($failedProducts),
            'failed_products' => $failedProducts
        ];
    }

    private function mapProductStateToWooCommerce($status)
    {
        $stateMap = [
            ProductStatusEnum::FOR_SALE->value => 'publish',
            ProductStatusEnum::DISCONTINUED->value => 'pending',
            ProductStatusEnum::IN_PROCESS->value => 'draft',
            ProductStatusEnum::OUT_OF_STOCK->value => 'draft'
        ];

        return $stateMap[$status] ?? 'draft';
    }

    /**
     * Find a product by its ID
     * This method should be implemented according to your application's database structure
     */
    private function findProductById($productId): Product|StoredItem|null
    {
        try {
            if (!$this->customer->is_fulfilment) {
                return Product::findOrFail($productId);
            } else {
                return StoredItem::findOrFail($productId);
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array']
        ];
    }

    public function asController(WooCommerceUser $wooCommerceUser, ActionRequest $request)
    {
        $this->initialisation($request);

        $this->handle($wooCommerceUser, $this->validatedData);
    }
}
