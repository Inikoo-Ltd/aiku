<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\WithPlatformListedProducts;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class GetShopifyListedProducts
{
    use AsAction;
    use WithPlatformListedProducts;

    private const int PAGE_SIZE = 50;

    private const int MAX_PAGES = 40;

    public const int VARIANTS_READ_PER_PRODUCT = 10;

    /**
     * Active and draft products: merchants prepare listings as drafts and link them before they
     * publish (CUS-1747), and uploading refuses a SKU a draft already has, pointing them here.
     * Archived products are retired and stay out.
     *
     * Shopify's product search cannot look inside variant SKUs the way the picker needs, so the
     * catalogue is read in full and searched here.
     *
     * @return array<int, array>
     */
    public function handle(?ShopifyUser $shopifyUser, string $query = '', int $offset = 0, int $limit = 50): array
    {
        if (!$shopifyUser) {
            return [];
        }

        return $this->pickListedProducts(
            'shopify-listed-products-'.$shopifyUser->id,
            fn () => $this->fetchListedProducts($shopifyUser),
            $query,
            $offset,
            $limit
        );
    }

    /**
     * @return array<int, array>
     */
    private function fetchListedProducts(ShopifyUser $shopifyUser): array
    {
        $client = $shopifyUser->getShopifyClient(true);

        if (!$client) {
            return [];
        }

        $graphqlQuery = <<<'QUERY'
        query listProducts($cursor: String) {
          products(first: 50, after: $cursor, query: "status:active OR status:draft") {
            pageInfo {
              hasNextPage
              endCursor
            }
            edges {
              node {
                id
                title
                handle
                vendor
                variants(first: 10) {
                  edges {
                    node {
                      sku
                    }
                  }
                }
                images(first: 1) {
                  edges {
                    node {
                      id
                      src
                    }
                  }
                }
              }
            }
          }
        }
        QUERY;

        $listedProducts = [];
        $cursor         = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            try {
                $response = $client->request($graphqlQuery, ['cursor' => $cursor]);
            } catch (\Exception $e) {
                Log::info('Shopify listed products failed: '.$e->getMessage());

                return $listedProducts;
            }

            if (!empty($response['errors']) || !isset($response['body'])) {
                return $listedProducts;
            }

            $body = $response['body']->toArray();

            if (Arr::has($body, 'errors')) {
                return $listedProducts;
            }

            foreach (Arr::get($body, 'data.products.edges', []) as $productEdge) {
                $listedProducts[] = $this->transformToStandardFormat(Arr::get($productEdge, 'node', []));
            }

            if (!Arr::get($body, 'data.products.pageInfo.hasNextPage')) {
                return $listedProducts;
            }

            $cursor = Arr::get($body, 'data.products.pageInfo.endCursor');
        }

        Log::warning('Shopify catalogue too large to read in full for the product picker', [
            'shopify_user_id' => $shopifyUser->id,
            'products_read'   => count($listedProducts)
        ]);

        return $listedProducts;
    }

    private function transformToStandardFormat(array $product): array
    {
        $skuList = array_values(array_filter(array_map(
            fn ($variantEdge) => Arr::get($variantEdge, 'node.sku'),
            Arr::get($product, 'variants.edges', [])
        )));

        return [
            'id'       => Arr::get($product, 'id'),
            'name'     => Arr::get($product, 'title'),
            'slug'     => Arr::get($product, 'handle'),
            'code'     => Arr::first($skuList),
            'vendor'   => Arr::get($product, 'vendor'),
            'images'   => array_map(
                fn ($imageEdge) => Arr::get($imageEdge, 'node'),
                Arr::get($product, 'images.edges', [])
            ),
            'sku_list' => $skuList,
            'number_variants' => count(Arr::get($product, 'variants.edges', []))
        ];
    }
}
