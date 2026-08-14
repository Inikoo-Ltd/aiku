<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class GetShopifyListedSkus
{
    use AsAction;

    private const int MAX_PAGES = 500;

    /**
     * Read the variants rather than the products: a SKU lives on the variant, and asking for
     * nested variant connections product by product blows through Shopify's query cost limit.
     * The page stays at 100 for the same reason, since retries are switched off on the client.
     *
     * @return array<string, string> lowercased sku => shopify product id
     */
    public function handle(ShopifyUser $shopifyUser): array
    {
        $client = $shopifyUser->getShopifyClient(true);

        if (!$client) {
            return [];
        }

        $query = <<<'QUERY'
        query listProductVariants($cursor: String) {
          productVariants(first: 100, after: $cursor) {
            pageInfo {
              hasNextPage
              endCursor
            }
            edges {
              node {
                sku
                product {
                  id
                }
              }
            }
          }
        }
        QUERY;

        $listedSkus = [];
        $cursor     = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            try {
                $response = $client->request($query, ['cursor' => $cursor]);
            } catch (\Exception $e) {

                return $listedSkus;
            }

            if (!empty($response['errors']) || !isset($response['body'])) {

                return $listedSkus;
            }

            $body = $response['body']->toArray();

            if (Arr::has($body, 'errors')) {

                return $listedSkus;
            }

            foreach (Arr::get($body, 'data.productVariants.edges', []) as $variantEdge) {
                $sku       = Arr::get($variantEdge, 'node.sku');
                $productId = Arr::get($variantEdge, 'node.product.id');

                if ($sku && $productId) {
                    $listedSkus[Str::lower($sku)] = $productId;
                }
            }

            if (!Arr::get($body, 'data.productVariants.pageInfo.hasNextPage')) {
                return $listedSkus;
            }

            $cursor = Arr::get($body, 'data.productVariants.pageInfo.endCursor');
        }

        return $listedSkus;
    }
}
