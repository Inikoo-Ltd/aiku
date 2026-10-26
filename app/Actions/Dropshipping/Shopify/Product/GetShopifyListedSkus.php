<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Models\Dropshipping\ShopifyUser;
use Gnikyt\BasicShopifyAPI\Contracts\GraphRequester;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class GetShopifyListedSkus
{
    use AsAction;

    private const int SKUS_PER_REQUEST = 50;

    private const int MAX_PAGES_PER_REQUEST = 20;

    private const int MAX_CONSECUTIVE_FAILED_REQUESTS = 3;

    private const array MATCHABLE_STATUSES = ['ACTIVE', 'DRAFT'];

    /**
     * Reads only the variants carrying one of the given SKUs, so the size of the merchant's catalogue does not matter.
     *
     * @param  array<int, string>  $skus
     *
     * @return array<string, string>
     */
    public function handle(ShopifyUser $shopifyUser, array $skus): array
    {
        $wantedSkus = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '') {
                $wantedSkus[Str::lower($sku)] ??= $sku;
            }
        }

        if (!$wantedSkus) {
            return [];
        }

        $client = $shopifyUser->getShopifyClient(true);

        if (!$client) {
            return [];
        }

        $query = <<<'QUERY'
        query listProductVariants($cursor: String, $query: String) {
          productVariants(first: 250, after: $cursor, query: $query) {
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

        $productIdsBySku    = [];
        $consecutiveFailures = 0;

        foreach (array_chunk($wantedSkus, self::SKUS_PER_REQUEST) as $skuChunk) {
            $search          = implode(' OR ', array_map(fn (string $sku) => 'sku:"'.addcslashes($sku, '"\\').'"', $skuChunk));
            $chunkProductIds = $this->readChunk($client, $query, $search, $wantedSkus, $shopifyUser);

            if ($chunkProductIds === null) {
                if (++$consecutiveFailures >= self::MAX_CONSECUTIVE_FAILED_REQUESTS) {
                    Log::warning('Shopify SKU lookup for bulk matching stopped after repeated failures', ['shopify_user_id' => $shopifyUser->id]);

                    break;
                }

                continue;
            }

            $consecutiveFailures = 0;
            $productIdsBySku     += $chunkProductIds;
        }

        return $this->resolveListedSkus($productIdsBySku);
    }

    /**
     * A chunk read only in part is discarded whole: a SKU whose second listing was on an unread page would otherwise look unambiguous.
     *
     * @return array<string, array<string, string>>|null
     */
    private function readChunk(GraphRequester $client, string $query, string $search, array $wantedSkus, ShopifyUser $shopifyUser): ?array
    {
        $productIdsBySku = [];
        $cursor          = null;

        for ($page = 0; $page < self::MAX_PAGES_PER_REQUEST; $page++) {
            $body = $this->requestPage($client, $query, $search, $cursor, $shopifyUser);

            if ($body === null) {
                return null;
            }

            foreach (Arr::get($body, 'data.productVariants.edges', []) as $variantEdge) {
                $sku       = Str::lower(trim((string) Arr::get($variantEdge, 'node.sku')));
                $productId = Arr::get($variantEdge, 'node.product.id');

                if (!isset($wantedSkus[$sku]) || !$productId || !in_array(Arr::get($variantEdge, 'node.product.status'), self::MATCHABLE_STATUSES, true)) {
                    continue;
                }

                $productIdsBySku[$sku][$productId] = $productId;
            }

            if (!Arr::get($body, 'data.productVariants.pageInfo.hasNextPage')) {
                return $productIdsBySku;
            }

            $cursor = Arr::get($body, 'data.productVariants.pageInfo.endCursor');
        }

        Log::warning('Shopify SKU lookup for bulk matching returned too many variants', ['shopify_user_id' => $shopifyUser->id, 'search' => $search]);

        return null;
    }

    private function requestPage(GraphRequester $client, string $query, string $search, ?string $cursor, ShopifyUser $shopifyUser): ?array
    {
        try {
            $response = $client->request($query, ['cursor' => $cursor, 'query' => $search]);
        } catch (\Exception $e) {
            Log::warning('Shopify SKU lookup for bulk matching failed', ['shopify_user_id' => $shopifyUser->id, 'search' => $search, 'error' => $e->getMessage()]);

            return null;
        }

        $body = isset($response['body']) ? $response['body']->toArray() : null;

        if (!empty($response['errors']) || $body === null || Arr::has($body, 'errors')) {
            Log::warning('Shopify SKU lookup for bulk matching failed', ['shopify_user_id' => $shopifyUser->id, 'search' => $search, 'errors' => $response['errors'] ?? Arr::get($body ?? [], 'errors')]);

            return null;
        }

        return $body;
    }

    private function resolveListedSkus(array $productIdsBySku): array
    {
        $listedSkus = [];

        foreach ($productIdsBySku as $sku => $productIds) {
            if (count($productIds) > 1) {
                continue;
            }

            $listedSkus[$sku] = Arr::first($productIds);
        }

        return $listedSkus;
    }
}
