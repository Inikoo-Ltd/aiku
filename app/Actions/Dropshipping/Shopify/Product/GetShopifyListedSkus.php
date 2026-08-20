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

    private const string ACTIVE_STATUS = 'ACTIVE';

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
                  status
                }
              }
            }
          }
        }
        QUERY;

        $productIdsBySku = [];
        $cursor          = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            try {
                $response = $client->request($query, ['cursor' => $cursor]);
            } catch (\Exception $e) {

                return $this->resolveListedSkus($shopifyUser, $productIdsBySku);
            }

            if (!empty($response['errors']) || !isset($response['body'])) {

                return $this->resolveListedSkus($shopifyUser, $productIdsBySku);
            }

            $body = $response['body']->toArray();

            if (Arr::has($body, 'errors')) {

                return $this->resolveListedSkus($shopifyUser, $productIdsBySku);
            }

            foreach (Arr::get($body, 'data.productVariants.edges', []) as $variantEdge) {
                $sku       = Arr::get($variantEdge, 'node.sku');
                $productId = Arr::get($variantEdge, 'node.product.id');

                if (!$sku || !$productId || Arr::get($variantEdge, 'node.product.status') !== self::ACTIVE_STATUS) {
                    continue;
                }

                $productIdsBySku[Str::lower($sku)][$productId] = $productId;
            }

            if (!Arr::get($body, 'data.productVariants.pageInfo.hasNextPage')) {
                return $this->resolveListedSkus($shopifyUser, $productIdsBySku);
            }

            $cursor = Arr::get($body, 'data.productVariants.pageInfo.endCursor');
        }

        Log::warning('Shopify catalogue too large to read in full for bulk matching', [
            'shopify_user_id' => $shopifyUser->id,
            'skus_read'       => count($productIdsBySku)
        ]);

        return $this->resolveListedSkus($shopifyUser, $productIdsBySku);
    }

    private function resolveListedSkus(ShopifyUser $shopifyUser, array $productIdsBySku): array
    {
        $listedSkus   = [];

        foreach ($productIdsBySku as $sku => $productIds) {
            if (count($productIds) > 1) {
                continue;
            }

            $listedSkus[$sku] = Arr::first($productIds);
        }

        return $listedSkus;
    }
}
