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
 * V3 keeps price and SKU on variants rather than the product, describes text as rich content
 * nodes, and guards writes with a revision, so nothing here maps one to one onto V1.
 *
 * @see https://dev.wix.com/docs/api-reference/business-solutions/stores/catalog-v3/introduction
 */
class WixCatalogV3 implements WixCatalog
{
    use WithWixShippingWeight;

    private const int PAGE_SIZE = 100;

    private const int MAX_PAGES = 59;

    public function __construct(private readonly WixUser $wixUser)
    {
    }

    public function createProduct(Portfolio $portfolio, ?int $quantity = null): array
    {
        $variant = [
            'price'              => $this->variantPrice($portfolio),
            'barcode'            => $portfolio->barcode,
            'sku'                => $portfolio->sku,
            'visible'            => true,
            'physicalProperties' => $this->variantPhysicalProperties($portfolio),
        ];

        if ($quantity !== null) {
            $variant['inventoryItem'] = ['quantity' => $quantity];
        }

        $path = $quantity === null ? '/stores/v3/products' : '/stores/v3/products-with-inventory';

        $response = $this->wixUser->makeApiRequest('POST', $path, [
            'product' => [
                'name'               => $this->productName($portfolio),
                'productType'        => 'PHYSICAL',
                'plainDescription'   => $this->plainDescription($portfolio->customer_description),
                'visible'            => true,
                'physicalProperties' => new \stdClass(),
                'variantsInfo'       => [
                    'variants' => [$variant],
                ],
            ],
        ]);

        if ($message = Arr::get($response, 'message')) {
            return ['message' => $message, 'field_violations' => Arr::get($response, 'field_violations', [])];
        }

        return ['id' => Arr::get($response, 'product.id')];
    }

    public function updateProduct(string $productId, Portfolio $portfolio): array
    {
        // V3 rejects a write that does not carry the revision it last handed out.
        $current = $this->rawProduct($productId);

        if (!$current) {
            return ['message' => 'Wix product '.$productId.' not found'];
        }

        $product = [
            'id'               => $productId,
            'revision'         => Arr::get($current, 'revision'),
            'name'             => $this->productName($portfolio),
            'plainDescription' => $this->plainDescription($portfolio->customer_description),
        ];

        if ($variantId = Arr::get($current, 'variantsInfo.variants.0.id')) {
            $product['variantsInfo'] = [
                'variants' => [[
                    'id'                 => $variantId,
                    'sku'                => $portfolio->sku,
                    'price'              => $this->variantPrice($portfolio),
                    'physicalProperties' => $this->variantPhysicalProperties($portfolio),
                ]],
            ];
        }

        $response = $this->wixUser->makeApiRequest('PATCH', "/stores/v3/products/$productId", [
            'product' => $product,
        ]);

        if ($message = Arr::get($response, 'message')) {
            return ['message' => $message, 'field_violations' => Arr::get($response, 'field_violations', [])];
        }

        return ['id' => $productId];
    }

    public function deleteProduct(string $productId): array
    {
        return $this->wixUser->makeApiRequest('DELETE', "/stores/v3/products/$productId");
    }

    public function getProduct(string $productId): ?array
    {
        $product = $this->rawProduct($productId);

        return $product ? $this->normalise($product) : null;
    }

    public function searchProducts(string $query = '', int $offset = 0, int $limit = 50): array
    {
        // V3 pages by cursor, so an offset is served by reading forward and discarding.
        $search = ['cursorPaging' => ['limit' => min($limit + $offset, self::PAGE_SIZE)]];

        if ($query !== '') {
            $search['search'] = [
                'expression' => $query,
                'fields'     => ['name'],
            ];
        }

        $response = $this->wixUser->makeApiRequest('POST', '/stores/v3/products/search', [
            'search' => $search,
        ]);

        return collect(Arr::get($response, 'products', []))
            ->slice($offset, $limit)
            ->map(fn ($product) => $this->normalise($product))
            ->values()
            ->all();
    }

    public function listedSkus(): array
    {
        $listedSkus = [];
        $cursor     = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $cursorPaging = ['limit' => self::PAGE_SIZE];

            if ($cursor) {
                $cursorPaging['cursor'] = $cursor;
            }

            $response = $this->wixUser->makeApiRequest('POST', '/stores/v3/products/search', [
                'search' => ['cursorPaging' => $cursorPaging],
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

            $cursor = Arr::get($response, 'pagingMetadata.cursors.next');

            if (!$cursor || count($products) < self::PAGE_SIZE) {
                return $listedSkus;
            }
        }

        Log::warning('Wix V3 catalogue too large to read in full for bulk matching', [
            'wix_user_id' => $this->wixUser->id,
            'skus_read'   => count($listedSkus),
        ]);

        return $listedSkus;
    }

    public function setInventory(string $productId, int $quantity): array
    {
        $inventoryItem = Arr::get($this->wixUser->makeApiRequest('POST', '/stores/v3/inventory-items/search', [
            'search' => [
                'filter'       => ['productId' => ['$eq' => $productId]],
                'cursorPaging' => ['limit' => 1],
            ],
        ]), 'inventoryItems.0');

        $inventoryItemId = Arr::get($inventoryItem, 'id');

        if (!$inventoryItemId) {
            return ['message' => 'Wix inventory item not found for product '.$productId];
        }

        return $this->wixUser->makeApiRequest('PATCH', "/stores/v3/inventory-items/$inventoryItemId", [
            'inventoryItem' => [
                'id'       => $inventoryItemId,
                'revision' => Arr::get($inventoryItem, 'revision'),
                'quantity' => $quantity,
            ],
            'reason'        => 'MANUAL',
        ]);
    }

    public function addProductMedia(string $productId, array $imageUrls): array
    {
        $current = $this->rawProduct($productId);

        if (!$current) {
            return ['message' => 'Wix product '.$productId.' not found'];
        }

        return $this->wixUser->makeApiRequest('PATCH', "/stores/v3/products/$productId", [
            'product' => [
                'id'       => $productId,
                'revision' => Arr::get($current, 'revision'),
                'media'    => [
                    'itemsInfo' => [
                        // The first item becomes the product's main media.
                        'items' => collect($imageUrls)->map(fn ($url) => ['url' => $url])->all(),
                    ],
                ],
            ],
        ]);
    }

    private function rawProduct(string $productId): ?array
    {
        return Arr::get($this->wixUser->makeApiRequest('GET', "/stores/v3/products/$productId"), 'product');
    }

    private function productName(Portfolio $portfolio): string
    {
        return $portfolio->customer_product_name ?: $portfolio->item_name;
    }

    /**
     * V3 carries weight per variant rather than per product.
     *
     * @return array<string, mixed>
     */
    private function variantPhysicalProperties(Portfolio $portfolio): array
    {
        $physicalProperties = [
            'productDimensions' => ['unit' => 'CM'],
            'packageDimensions' => ['unit' => 'CM'],
        ];

        if ($weight = $this->wixShippingWeight($portfolio)) {
            $physicalProperties['weight'] = $weight;
        }

        return $physicalProperties;
    }

    /**
     * V3 money is a FixedMonetaryAmount, so the amount travels as a string rather than a float.
     *
     * compareAtPrice is deliberately absent: it renders as a struck-through "was" price to the
     * seller's own shoppers, and the only price we hold besides theirs is our wholesale price,
     * which must never be published.
     *
     * @return array{actualPrice: array{amount: string}}
     */
    private function variantPrice(Portfolio $portfolio): array
    {
        return [
            'actualPrice' => [
                'amount' => number_format((float) $portfolio->customer_price, 2, '.', ''),
            ],
        ];
    }

    /**
     * V3 stores descriptions as Ricos rich content, but accepts HTML through plainDescription
     * and converts it itself, which is far less brittle than hand building Ricos nodes.
     */
    private function plainDescription(?string $text): string
    {
        $text = trim((string) $text);

        if ($text === '') {
            return '';
        }

        return $text === strip_tags($text) ? '<p>'.e($text).'</p>' : $text;
    }

    /**
     * @return array<int, string>
     */
    private function skusOf(array $product): array
    {
        $skus = array_filter([
            Arr::get($product, 'minVariantPriceInfo.sku'),
            Arr::get($product, 'sku'),
        ]);

        foreach (Arr::get($product, 'variantsInfo.variants', []) as $variant) {
            if ($variantSku = Arr::get($variant, 'sku')) {
                $skus[] = $variantSku;
            }
        }

        return array_unique($skus);
    }

    private function normalise(array $product): array
    {
        return [
            'id'    => Arr::get($product, 'id'),
            'name'  => Arr::get($product, 'name'),
            'sku'   => Arr::get($product, 'variantsInfo.variants.0.sku')
                ?? Arr::get($product, 'minVariantPriceInfo.sku'),
            'price' => Arr::get($product, 'variantsInfo.variants.0.price.actualPrice')
                ?? Arr::get($product, 'actualPriceRange.minValue.amount'),
            'image' => Arr::get($product, 'media.main.image'),
        ];
    }
}
