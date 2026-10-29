<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Sep 2026 15:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Dropshipping\Shopify\CheckShopifyChannel;
use App\Actions\Dropshipping\WithPortfolioErrorResponse;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Matching a Shopify product with several variants, one of them carrying our sku, links the portfolio
 * to that variant instead of adding ours next to it (HELP-3180, INI-028 step 4, CUS-1788): the
 * merchant combined our products as variants of one listing. Two variants carrying our sku are refused,
 * and so is a product with several variants none of which carries it (CUS-1809): Shopify refuses to add
 * our variant to a product with options, answering "You need to add option values for ...". A
 * single-variant product returns null and goes through StoreShopifyProductVariant as before.
 *
 * The merchant's sku and barcode are never written, nor the price unless the merchant asked us to
 * manage it. A variant stocked at any other location, even with 0, is refused: Shopify could route its orders
 * there and they would never reach us.
 */
class AdoptShopifyProductVariant
{
    use AsAction;
    use WithPortfolioErrorResponse;

    private const int VARIANTS_FOLLOWED_PER_PRODUCT = 50;

    private const int MAX_VARIANT_PAGES = 10;

    /**
     * @return array{0: bool, 1: string}|null null when the product is not one to adopt a variant of
     */
    public function handle(Portfolio $portfolio, string $shopifyProductId, ?bool $priceManagedByUs = null): ?array
    {
        $shopifyUser = $portfolio->customerSalesChannel?->user;

        if (!$shopifyUser instanceof ShopifyUser || !CheckIfShopifyProductIDIsValid::run($shopifyProductId)) {
            return null;
        }

        try {
            $variants = $this->getVariants($shopifyUser, $shopifyProductId);

            if (count($variants) < 2) {
                return null;
            }

            $matchingVariants = self::variantsCarryingPortfolioSku($portfolio, $variants);

            if ($matchingVariants === []) {
                return $this->fail($portfolio, 'None of the '.count($variants).' variants of this Shopify product has the SKU '.$portfolio->item_code.'. Give the variant that SKU in Shopify, then match again');
            }

            if (count($matchingVariants) > 1) {
                return $this->fail($portfolio, 'More than one variant of this Shopify product has the sku '.$portfolio->sku.', so it can not be linked. Give each variant its own sku and try again');
            }

            $variant = $matchingVariants[0];

            if ($variant['position'] >= self::VARIANTS_FOLLOWED_PER_PRODUCT) {
                return $this->fail($portfolio, 'This Shopify product has too many variants, only the first '.self::VARIANTS_FOLLOWED_PER_PRODUCT.' can be linked');
            }

            if (!$shopifyUser->shopify_location_id) {
                CheckShopifyChannel::run($portfolio->customerSalesChannel);
                $shopifyUser->refresh();
            }

            if (!$shopifyUser->shopify_location_id) {
                return $this->fail($portfolio, 'No Shopify location, the AW fulfilment service is not installed on this store so stock can not be sent');
            }

            $holder = LinkShopifyPortfolio::variantHolder($portfolio->customerSalesChannel, $variant['id'], $portfolio);

            if ($holder) {
                return $this->fail($portfolio, 'This Shopify variant is already linked to '.$holder->item_code.' in this channel');
            }

            $otherLocationRefusal = $this->otherLocationRefusal($shopifyUser, $variant['id']);

            if ($otherLocationRefusal) {
                return $this->fail($portfolio, $otherLocationRefusal);
            }

            $this->trackVariantStock($shopifyUser, $portfolio, $shopifyProductId, $variant['id']);
        } catch (Throwable $e) {
            return $this->fail($portfolio, $e->getMessage());
        }

        if ($portfolio->isShopifyVariantAdopted() && $portfolio->platform_product_variant_id !== $variant['id']) {
            DeactivateShopifyProduct::run($portfolio);
        }

        [$linked, $refusal] = LinkShopifyPortfolio::run($portfolio, $shopifyProductId, $variant['id'], [
            'platform_status' => false,
            'platform_sku'    => null,
            'errors_response' => null
        ]);

        if (!$linked) {
            return $this->fail($portfolio, $refusal);
        }

        $portfolio->markShopifyVariantAdopted(true);

        if ($priceManagedByUs !== null) {
            $portfolio->markShopifyPriceManagedByUs($priceManagedByUs);
        }

        [$stocked, $stockedMessage] = StoreShopifyLocationToProductVariant::run($portfolio->refresh());

        if ($stocked && $portfolio->isShopifyPriceManagedByUs()) {
            [$priced, $pricedMessage] = UpdateShopifyProductVariant::run($portfolio->refresh());

            if (!$priced) {
                return [false, $pricedMessage];
            }
        }

        return [$stocked, $stocked ? $variant['id'] : $stockedMessage];
    }

    /**
     * Shopify can route an order to any location the variant is stocked at, even one holding 0
     * (seen on an admin draft order), so any location other than ours is refused.
     */
    private function otherLocationRefusal(ShopifyUser $shopifyUser, string $variantId): ?string
    {
        $query = <<<'QUERY'
        query getVariantStockLocations($id: ID!) {
          productVariant(id: $id) {
            inventoryItem {
              inventoryLevels(first: 50) {
                edges {
                  node {
                    location {
                      id
                      name
                    }
                  }
                }
              }
            }
          }
        }
        QUERY;

        $body = $this->request($shopifyUser, $query, ['id' => $variantId]);

        foreach (Arr::get($body, 'data.productVariant.inventoryItem.inventoryLevels.edges', []) as $levelEdge) {
            if (self::sameLocation(Arr::get($levelEdge, 'node.location.id'), $shopifyUser->shopify_location_id)) {
                continue;
            }

            return 'This Shopify variant is also stocked at your location "'.Arr::get($levelEdge, 'node.location.name').'". Shopify could send its orders there and we would never receive them. Remove that location from the variant in Shopify, then match again';
        }

        return null;
    }

    private static function sameLocation(?string $locationId, ?string $ourLocationId): bool
    {
        return $locationId !== null && $ourLocationId !== null
            && Str::afterLast($locationId, '/') === Str::afterLast($ourLocationId, '/');
    }

    /**
     * @param  list<array{id: string, sku: string, position?: int}>  $variants
     * @return list<array{id: string, sku: string, position?: int}>
     */
    public static function variantsCarryingPortfolioSku(Portfolio $portfolio, array $variants): array
    {
        $item    = $portfolio->item;
        $ownSkus = array_filter([
            Str::lower((string) $portfolio->sku),
            $item instanceof Product ? Str::lower((string) $item->code) : null
        ]);

        return array_values(array_filter(
            $variants,
            fn (array $variant) => in_array(Str::lower(trim($variant['sku'])), $ownSkus, true)
        ));
    }

    /**
     * @return list<array{id: string, sku: string, position?: int}>
     */
    private function getVariants(ShopifyUser $shopifyUser, string $shopifyProductId): array
    {
        $query = <<<'QUERY'
        query getProductVariantsToAdopt($id: ID!, $cursor: String) {
          product(id: $id) {
            variants(first: 250, after: $cursor) {
              pageInfo {
                hasNextPage
                endCursor
              }
              edges {
                node {
                  id
                  sku
                }
              }
            }
          }
        }
        QUERY;

        $variants = [];
        $cursor   = null;

        for ($page = 0; $page < self::MAX_VARIANT_PAGES; $page++) {
            $body = $this->request($shopifyUser, $query, ['id' => $shopifyProductId, 'cursor' => $cursor]);

            foreach (Arr::get($body, 'data.product.variants.edges', []) as $variantEdge) {
                $variants[] = [
                    'id'       => (string) Arr::get($variantEdge, 'node.id'),
                    'sku'      => (string) Arr::get($variantEdge, 'node.sku'),
                    'position' => count($variants)
                ];
            }

            $cursor = Arr::get($body, 'data.product.variants.pageInfo.endCursor');

            if (!Arr::get($body, 'data.product.variants.pageInfo.hasNextPage') || !$cursor) {
                return $variants;
            }
        }

        throw new \RuntimeException('This Shopify product has too many variants to be read');
    }

    private function trackVariantStock(ShopifyUser $shopifyUser, Portfolio $portfolio, string $shopifyProductId, string $variantId): void
    {
        $mutation = <<<'MUTATION'
        mutation ProductVariantAdopt($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
          productVariantsBulkUpdate(productId: $productId, variants: $variants) {
            productVariants {
              id
            }
            userErrors {
              field
              message
            }
          }
        }
        MUTATION;

        $variant = [
            'id'            => $variantId,
            'inventoryItem' => ['tracked' => true]
        ];

        $item = $portfolio->item;
        if ($item instanceof Product && $item->orgStocks->contains('is_on_demand', true)) {
            $variant['inventoryPolicy'] = 'CONTINUE';
        }

        $body = $this->request($shopifyUser, $mutation, ['productId' => $shopifyProductId, 'variants' => [$variant]]);

        $userErrors = Arr::get($body, 'data.productVariantsBulkUpdate.userErrors', []);
        if (!empty($userErrors)) {
            throw new \RuntimeException('User errors: '.json_encode($userErrors));
        }
    }

    private function request(ShopifyUser $shopifyUser, string $graphql, array $variables): array
    {
        $client = $shopifyUser->getShopifyClient(true);

        if (!$client) {
            throw new \RuntimeException('Failed to initialize Shopify GraphQL client');
        }

        $response = $client->request($graphql, $variables);

        if (!empty($response['errors']) || !isset($response['body'])) {
            throw new \RuntimeException('Error in API response: '.json_encode($response['errors'] ?? []));
        }

        return $response['body']->toArray();
    }

    /**
     * @return array{0: false, 1: string}
     */
    private function fail(Portfolio $portfolio, string $message): array
    {
        UpdatePortfolio::run($portfolio, [
            'errors_response' => $this->portfolioErrorResponse($message) ?? ['message' => $message]
        ]);

        return [false, $message];
    }
}
