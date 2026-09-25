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
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * A merchant who already sells our products as the variants of one Shopify product (one per colour
 * or design, each carrying our sku) needs the portfolio linked to that variant: creating another
 * variant, which is what matching does otherwise, would duplicate it (HELP-3180).
 *
 * Only products with several variants are handled here, a product with a single variant keeps going
 * through StoreShopifyProductVariant. The merchant's price, sku and barcode are never written.
 */
class AdoptShopifyProductVariant
{
    use AsAction;
    use WithPortfolioErrorResponse;

    private const int VARIANTS_FOLLOWED_PER_PRODUCT = 50;

    private const int MAX_VARIANT_PAGES = 10;

    /**
     * @return array{0: bool, 1: string}|null null when the product has a single variant, which is not adopted
     */
    public function handle(Portfolio $portfolio, string $shopifyProductId): ?array
    {
        $shopifyUser = $portfolio->customerSalesChannel?->user;

        if (!$shopifyUser instanceof ShopifyUser || !CheckIfShopifyProductIDIsValid::run($shopifyProductId)) {
            return null;
        }

        if (!self::isEnabledFor($portfolio->customerSalesChannel)) {
            return null;
        }

        try {
            $variants = $this->getVariants($shopifyUser, $shopifyProductId);

            if (count($variants) < 2) {
                return null;
            }

            $matchingVariants = self::variantsCarryingPortfolioSku($portfolio, $variants);

            if (empty($matchingVariants)) {
                return $this->fail($portfolio, 'None of the variants of this Shopify product has the sku '.$portfolio->sku.'. Set that sku on the variant you want to link and try again');
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

            $linkedElsewhere = Portfolio::where('customer_sales_channel_id', $portfolio->customer_sales_channel_id)
                ->where('platform_product_variant_id', $variant['id'])
                ->where('id', '!=', $portfolio->id)
                ->exists();

            if ($linkedElsewhere) {
                return $this->fail($portfolio, 'The variant with the sku '.$portfolio->sku.' is already linked to another product in this channel');
            }

            $this->trackVariantStock($shopifyUser, $portfolio, $shopifyProductId, $variant['id']);
        } catch (Throwable $e) {
            return $this->fail($portfolio, $e->getMessage());
        }

        if ($portfolio->isShopifyVariantAdopted() && $portfolio->platform_product_variant_id !== $variant['id']) {
            DeactivateShopifyProduct::run($portfolio);
        }

        UpdatePortfolio::run($portfolio, [
            'platform_product_id'         => $shopifyProductId,
            'platform_product_variant_id' => $variant['id'],
            'platform_status'             => false,
            'errors_response'             => null
        ]);
        $portfolio->markShopifyVariantAdopted(true);

        [$stocked, $stockedMessage] = StoreShopifyLocationToProductVariant::run($portfolio->refresh());

        return [$stocked, $stocked ? $variant['id'] : $stockedMessage];
    }

    public static function isEnabledFor(?CustomerSalesChannel $customerSalesChannel): bool
    {
        return (bool) data_get($customerSalesChannel?->settings, 'shopify.link_existing_variants', false);
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
            fn (array $variant) => in_array(Str::lower($variant['sku']), $ownSkus, true)
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
