<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Catalog;

use App\Actions\Dropshipping\Wix\Traits\WithWixShippingWeight;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WixUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * @see https://dev.wix.com/docs/api-reference/business-solutions/stores/catalog-v1/catalog/introduction
 */
class WixCatalogV1 implements WixCatalog
{
    use WithWixShippingWeight;

    private const int PAGE_SIZE = 100;

    private const int MAX_PAGES = 59;

    public function __construct(private readonly WixUser $wixUser)
    {
    }

    public function createProduct(Portfolio $portfolio, ?int $quantity = null): array
    {
        $response = $this->wixUser->makeApiRequest('POST', '/stores/v1/products', [
            'product' => array_filter([
                'name'           => $this->productName($portfolio),
                'productType'    => 'physical',
                'sku'            => $portfolio->sku,
                'description'    => $portfolio->customer_description ?: '',
                'visible'        => true,
                'priceData'      => ['price' => (float) $portfolio->customer_price],
                'manageVariants' => false,
                // V1 keeps weight on the product itself rather than on the variant.
                'weight'         => $this->wixShippingWeight($portfolio),
            ], fn ($value) => $value !== null),
        ]);

        if ($message = Arr::get($response, 'message')) {
            return ['message' => $message, 'field_violations' => Arr::get($response, 'field_violations', [])];
        }

        $productId = Arr::get($response, 'product.id');

        // V1 has no create-with-inventory endpoint, so stock follows the product.
        if ($productId && $quantity !== null) {
            $this->setInventory($productId, $quantity);
        }

        return ['id' => $productId];
    }

    public function updateProduct(string $productId, Portfolio $portfolio): array
    {
        $response = $this->wixUser->makeApiRequest('PATCH', "/stores/v1/products/$productId", [
            'product' => array_filter([
                'name'        => $this->productName($portfolio),
                'description' => $portfolio->customer_description ?: '',
                'sku'         => $portfolio->sku,
                'priceData'   => ['price' => (float) $portfolio->customer_price],
                'weight'      => $this->wixShippingWeight($portfolio),
            ], fn ($value) => $value !== null),
        ]);

        if ($message = Arr::get($response, 'message')) {
            return ['message' => $message, 'field_violations' => Arr::get($response, 'field_violations', [])];
        }

        return ['id' => $productId];
    }

    public function deleteProduct(string $productId): array
    {
        return $this->wixUser->makeApiRequest('DELETE', "/stores/v1/products/$productId");
    }

    public function getProduct(string $productId): ?array
    {
        $product = Arr::get($this->wixUser->makeApiRequest('GET', "/stores/v1/products/$productId"), 'product');

        return $product ? $this->normalise($product) : null;
    }

    public function searchProducts(string $query = '', int $offset = 0, int $limit = 50): array
    {
        $catalogQuery = ['paging' => ['limit' => $limit, 'offset' => $offset]];

        if ($query !== '') {
            $catalogQuery['filter'] = json_encode(['name' => ['$contains' => $query]]);
        }

        $response = $this->wixUser->makeApiRequest('POST', '/stores/v1/products/query', [
            'query' => $catalogQuery,
        ]);

        return collect(Arr::get($response, 'products', []))
            ->map(fn ($product) => $this->normalise($product))
            ->all();
    }

    public function listedSkus(): array
    {
        $listedSkus = [];

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = $this->wixUser->makeApiRequest('POST', '/stores/v1/products/query', [
                'query' => ['paging' => ['limit' => self::PAGE_SIZE, 'offset' => $page * self::PAGE_SIZE]],
            ]);

            $products = Arr::get($response, 'products');

            if (!is_array($products)) {
                return $listedSkus;
            }

            foreach ($products as $product) {
                $productId = Arr::get($product, 'id');

                if (!$productId) {
                    continue;
                }

                foreach ($this->skusOf($product) as $sku) {
                    $listedSkus[Str::lower($sku)] = (string) $productId;
                }
            }

            if (count($products) < self::PAGE_SIZE) {
                return $listedSkus;
            }
        }

        Log::warning('Wix V1 catalogue too large to read in full for bulk matching', [
            'wix_user_id' => $this->wixUser->id,
            'skus_read'   => count($listedSkus),
        ]);

        return $listedSkus;
    }

    public function setInventory(string $productId, int $quantity): array
    {
        $inventoryItemId = Arr::get($this->wixUser->makeApiRequest('POST', '/stores/v2/inventoryItems/query', [
            'query' => [
                'filter' => json_encode(['productId' => ['$eq' => $productId]]),
                'paging' => ['limit' => 1, 'offset' => 0],
            ],
        ]), 'inventoryItems.0.id');

        if (!$inventoryItemId) {
            return ['message' => 'Wix inventory item not found for product '.$productId];
        }

        return $this->wixUser->makeApiRequest('PATCH', "/stores/v2/inventoryItems/$inventoryItemId", [
            'inventoryItem' => [
                'trackQuantity' => true,
                'variants'      => [[
                    'variantId' => '00000000-0000-0000-0000-000000000000',
                    'inStock'   => $quantity > 0,
                    'quantity'  => $quantity,
                ]],
            ],
        ]);
    }

    public function addProductMedia(string $productId, array $imageUrls): array
    {
        return $this->wixUser->makeApiRequest('POST', "/stores/v1/products/$productId/media", [
            'media' => collect($imageUrls)->map(fn ($url) => ['url' => $url])->all(),
        ]);
    }

    private function productName(Portfolio $portfolio): string
    {
        return $portfolio->customer_product_name ?: $portfolio->item_name;
    }

    /**
     * @return array<int, string>
     */
    private function skusOf(array $product): array
    {
        $skus = array_filter([Arr::get($product, 'sku')]);

        foreach (Arr::get($product, 'variants', []) as $variant) {
            if ($variantSku = Arr::get($variant, 'variant.sku')) {
                $skus[] = $variantSku;
            }
        }

        return $skus;
    }

    private function normalise(array $product): array
    {
        $sku   = Arr::get($product, 'sku');
        $image = Arr::get($product, 'media.mainMedia.image.url');

        return [
            'id'     => Arr::get($product, 'id'),
            'name'   => Arr::get($product, 'name'),
            'sku'    => $sku,
            'code'   => $sku,
            'price'  => Arr::get($product, 'priceData.price'),
            'image'  => $image,
            'images' => $image ? [['src' => $image]] : [],
        ];
    }
}
