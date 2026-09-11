<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\PlatformOutboundGuard;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Reads the whole Shopify catalogue in one paginated sweep so a portfolio audit needs no
 * per-product call: checking 7,000 portfolios one by one is what exhausts the rate limit
 * and leaves the audit itself half answered.
 *
 * `complete` says whether the sweep reached the end of the catalogue. A partial read cannot
 * tell "not in Shopify" from "not read yet", so callers must refuse to draw conclusions
 * from an incomplete snapshot.
 */
class GetShopifyCatalogueSnapshot
{
    use AsAction;

    private const int PAGE_SIZE = 100;

    private const int MAX_PAGES = 500;

    private const string ACTIVE_STATUS = 'ACTIVE';

    /**
     * @return array{complete: bool, variants_read: int, products: array<string, array{status: string|null, variants: array<int, array{id: string|null, sku: string|null, at_location: bool}>}>, product_ids_by_sku: array<string, array<string, bool>>}
     */
    public function handle(ShopifyUser $shopifyUser): array
    {
        $snapshot = [
            'complete'           => false,
            'reason'             => null,
            'variants_read'      => 0,
            'products'           => [],
            'product_ids_by_sku' => []
        ];

        if (!$shopifyUser->shopify_location_id) {
            $snapshot['reason'] = 'The channel has no Shopify fulfilment location id stored';

            return $snapshot;
        }

        $client = $shopifyUser->getShopifyClient(true);

        if (!$client) {
            $snapshot['reason'] = 'Could not build a Shopify client for this channel, its access token is probably expired or revoked';

            return $snapshot;
        }

        $query = <<<'QUERY'
        query auditProductVariants($cursor: String, $locationId: ID!) {
          productVariants(first: 100, after: $cursor) {
            pageInfo {
              hasNextPage
              endCursor
            }
            edges {
              node {
                id
                sku
                product {
                  id
                  status
                }
                inventoryItem {
                  inventoryLevel(locationId: $locationId) {
                    id
                  }
                }
              }
            }
          }
        }
        QUERY;

        $cursor = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $body = $this->readPage($client, $query, $cursor, $shopifyUser->shopify_location_id, $snapshot);

            if ($body === null) {
                return $snapshot;
            }

            foreach (Arr::get($body, 'data.productVariants.edges', []) as $variantEdge) {
                $this->collectVariant($snapshot, Arr::get($variantEdge, 'node', []));
            }

            if (!Arr::get($body, 'data.productVariants.pageInfo.hasNextPage')) {
                $snapshot['complete'] = true;

                return $snapshot;
            }

            $cursor = Arr::get($body, 'data.productVariants.pageInfo.endCursor');
        }

        $snapshot['reason'] = 'The catalogue is larger than the '.(self::MAX_PAGES * self::PAGE_SIZE).' variant audit cap';

        Log::warning('Shopify catalogue exceeds the audit page cap', [
            'shopify_user_id' => $shopifyUser->id,
            'variants_read'   => $snapshot['variants_read'],
            'page_cap'        => self::MAX_PAGES * self::PAGE_SIZE
        ]);

        return $snapshot;
    }

    private function readPage($client, string $query, ?string $cursor, string $locationId, array &$snapshot): ?array
    {
        try {
            $response = $client->request($query, ['cursor' => $cursor, 'locationId' => $locationId]);
        } catch (\Exception $e) {
            $snapshot['reason'] = 'Shopify request threw: '.$e->getMessage();

            Log::warning('Shopify catalogue audit sweep failed: '.$e->getMessage());

            return null;
        }

        if (!empty($response['errors']) || !isset($response['body'])) {
            $snapshot['reason'] = 'Shopify rejected the catalogue query: '.json_encode(Arr::get($response, 'errors'));

            return null;
        }

        $body = $response['body']->toArray();

        if (Arr::has($body, 'errors')) {
            $snapshot['reason'] = 'Shopify returned errors: '.json_encode(Arr::get($body, 'errors'));

            Log::warning('Shopify catalogue audit sweep returned errors', ['errors' => Arr::get($body, 'errors')]);

            return null;
        }

        return $body;
    }

    private function collectVariant(array &$snapshot, array $variant): void
    {
        $productId = Arr::get($variant, 'product.id');

        if (!$productId) {
            return;
        }

        $sku = Arr::get($variant, 'sku');

        $snapshot['variants_read']++;
        $snapshot['products'][$productId]['status']     = Arr::get($variant, 'product.status');
        $snapshot['products'][$productId]['variants'][] = [
            'id'          => Arr::get($variant, 'id'),
            'sku'         => $sku,
            'at_location' => (bool) Arr::get($variant, 'inventoryItem.inventoryLevel.id')
        ];

        if ($sku) {
            $snapshot['product_ids_by_sku'][Str::lower($sku)][$productId] = Arr::get($variant, 'product.status') === self::ACTIVE_STATUS;
        }
    }
}
