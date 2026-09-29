<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Jul 2025 08:28:03 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Dropshipping\Shopify\CheckShopifyChannel;
use App\Actions\Dropshipping\WithPortfolioErrorResponse;
use App\Actions\Dropshipping\WooCommerce\Product\UpdateWooCustomerSalesChannelPortfolio;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sentry;

class StoreShopifyProductVariant extends RetinaAction
{
    use WithActionUpdate;
    use WithPortfolioErrorResponse;


    /** level 1: upload includes price
     *  level 0: match excludes price
     */
    public function handle(Portfolio $portfolio, $level = 1): array
    {
        if ($portfolio->isShopifyVariantAdopted()) {
            return [false, 'This portfolio is linked to a variant the merchant already had, another variant is never created for it'];
        }

        $customerSalesChannel = $portfolio->customerSalesChannel;

        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $customerSalesChannel->user;

        $client = $shopifyUser->getShopifyClient(true); // Get GraphQL client

        if (!$client) {
            Log::error("Failed to initialize Shopify GraphQL client");

            return [false, 'Failed to initialize Shopify GraphQL client'];
        }

        /** @var Product $product */
        $product = $portfolio->item;


        $productID = $portfolio->platform_product_id;

        if (! $portfolio->sku) {
            return [false, 'Portfolio does not contains SKU'];
        }

        if (!$productID) {
            Log::error("No Shopify product ID found in portfolio C");

            return [false, 'No Shopify product ID found in portfolio'];
        }

        if (!CheckIfShopifyProductIDIsValid::run($productID)) {
            return [false, 'Invalid Shopify product ID'];
        }

        if (!$shopifyUser->shopify_location_id) {
            CheckShopifyChannel::run($customerSalesChannel);
            $shopifyUser->refresh();
        }

        if (!$shopifyUser->shopify_location_id) {
            $errorMessage = 'No Shopify location, the AW fulfilment service is not installed on this store so stock can not be sent';

            UpdatePortfolio::run($portfolio, [
                'errors_response' => $this->portfolioErrorResponse($errorMessage)
            ]);

            return [false, $errorMessage];
        }


        $replacedVariantOwner = self::ownerOfStandaloneVariantThatWouldBeReplaced($portfolio, $productID);

        if ($replacedVariantOwner !== null) {
            $errorMessage = self::replacedVariantMessage($replacedVariantOwner);

            $sharesItsVariantWithAnotherPortfolio = $portfolio->platform_product_variant_id && Portfolio::where('customer_sales_channel_id', $portfolio->customer_sales_channel_id)
                ->where('id', '!=', $portfolio->id)
                ->where('platform_product_variant_id', $portfolio->platform_product_variant_id)
                ->exists();

            if ($sharesItsVariantWithAnotherPortfolio) {
                $errorMessage = self::replacedVariantMessage($replacedVariantOwner, false);
            }

            UpdatePortfolio::run($portfolio, array_merge(
                ['errors_response' => $this->portfolioErrorResponse($errorMessage)],
                $replacedVariantOwner === false || $sharesItsVariantWithAnotherPortfolio ? [] : [
                    'platform_product_id'         => null,
                    'platform_product_variant_id' => null,
                    'platform_status'             => false,
                    'sku'                         => ($portfolio->item ? StorePortfolio::make()->getSKU($portfolio->item) : null) ?? $portfolio->sku,
                ]
            ));

            return [false, $errorMessage];
        }

        try {
            // GraphQL mutation to update product variants
            $mutation = <<<'MUTATION'
            mutation ProductVariantsCreate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
              productVariantsBulkCreate(productId: $productId, strategy: REMOVE_STANDALONE_VARIANT, variants: $variants) {
                productVariants {
                  id
                  title
                }
                userErrors {
                  field
                  message
                }
              }
            }
            MUTATION;


            $quantityToSend = UpdateWooCustomerSalesChannelPortfolio::quantityToSend($product, $customerSalesChannel);

            $inventoryItem = [
                'cost'    => $product->price,
                'sku'     => $portfolio->sku,
                'tracked' => true,

            ];

            $shippingWeight = $product->gross_weight ?: $product->marketing_weight;

            if ($shippingWeight) {
                $inventoryItem['measurement'] = [
                    'weight' => [
                        'unit'  => 'GRAMS',
                        'value' => $shippingWeight

                    ]
                ];
            }

            // Prepare variables for the mutation
            $variants = [
                [
                    'barcode'             => $portfolio->barcode,
                    'inventoryItem'       => $inventoryItem,
                    'inventoryQuantities' => [
                        'availableQuantity' => $quantityToSend,
                        'locationId'        => $shopifyUser->shopify_location_id
                    ]
                ]
            ];

            $currentVariant = FindSpecificShopifyProductVariant::run($customerSalesChannel, $portfolio->platform_product_id);

            if (Arr::get($currentVariant, 'price') > 0 && Arr::get($currentVariant, 'price') !== $portfolio->customer_price) {
                data_set($variants[0], 'price', Arr::get($currentVariant, 'price'));
                data_set($variants[0], 'compareAtPrice', Arr::get($currentVariant, 'price'));
            } else {
                data_set($variants[0], 'price', $portfolio->customer_price);
                data_set($variants[0], 'compareAtPrice', $portfolio->customer_price);
            }

            $isOnDemand = false;
            foreach ($product->orgStocks as $orgStock) {
                if ($orgStock->is_on_demand) {
                    $isOnDemand = true;
                }
            }

            if ($isOnDemand) {
                data_set($variants[0], 'inventoryPolicy', 'CONTINUE');
            }

            $variables = [
                'productId' => $productID,
                'variants'  => $variants,
            ];


            // Make the GraphQL request
            $response = $client->request($mutation, $variables);

            if (!empty($response['errors']) || !isset($response['body'])) {
                $errorMessage = 'Error in API response: '.json_encode($response['errors'] ?? []);
                UpdatePortfolio::run($portfolio, [
                    'errors_response' => $this->portfolioErrorResponse($errorMessage)
                ]);
                Log::error("Product variant update failed A: ".$errorMessage);

                return [false, $errorMessage];
            }

            $body = $response['body']->toArray();

            if (!empty($body['data']['productVariantsBulkCreate']['userErrors'])) {
                $errors       = $body['data']['productVariantsBulkCreate']['userErrors'];
                $errorMessage = 'User errors: '.json_encode($errors);

                UpdatePortfolio::run($portfolio, [
                    'errors_response' => $this->portfolioErrorResponse($errorMessage)
                ]);
                Log::error("Product variant update failed B: ".$errorMessage);


                return [false, $errorMessage];
            }


            $variantId = Arr::get($body, 'data.productVariantsBulkCreate.productVariants.0.id');
            if ($variantId) {
                UpdatePortfolio::run($portfolio, [
                    'platform_product_variant_id' => $variantId,
                    'last_stock_value'            => $quantityToSend,
                    'stock_last_updated_at'       => now(),
                    'errors_response'             => null
                ]);
            }

            SaveShopifyProductData::run($portfolio);

            return [true, $variantId];
        } catch (Exception $e) {
            Sentry::captureException($e);
            UpdatePortfolio::run($portfolio, [
                'errors_response' => $this->portfolioErrorResponse($e->getMessage())
            ]);

            return [false, $e->getMessage()];
        }
    }

    public function getCommandSignature(): string
    {
        return 'shopify:create_product_variant {portfolio_id}';
    }


    public function asCommand(Command $command): void
    {
        $portfolio = Portfolio::find($command->argument('portfolio_id'));

        if (!$portfolio) {
            $command->error("Portfolio not found");

            return;
        }

        list($status, $result) = $this->handle($portfolio);
        if ($status) {
            $command->info("\nProduct variant updated successfully");
            print_r($result);
        }
    }

    /**
     * productVariantsBulkCreate with REMOVE_STANDALONE_VARIANT deletes the only variant of a product.
     * When other active portfolios of the channel are linked to that product, the only variant is this
     * portfolio's when it carries its product code, or when it is its stored variant or carries its sku and
     * no other portfolio holds that variant or has that sku as its code. Otherwise it is another product's
     * listing: adding this one would silently take it away, so the caller refuses and unlinks this portfolio
     * from that product, giving it back its own sku so it can get a listing of its own. When this portfolio
     * holds the same variant as another one it can not be told whose it is, so it is refused but left linked.
     *
     * @return string|false|null  the product code of the portfolio the variant belongs to, false when it could not be checked
     */
    public static function ownerOfStandaloneVariantThatWouldBeReplaced(Portfolio $portfolio, string $productId): string|false|null
    {
        $siblings = Portfolio::where('customer_sales_channel_id', $portfolio->customer_sales_channel_id)
            ->where('id', '!=', $portfolio->id)
            ->where('status', true)
            ->where('platform_product_id', $productId)
            ->orderBy('id')
            ->get(['id', 'item_code', 'sku', 'platform_product_variant_id']);

        if ($siblings->isEmpty()) {
            return null;
        }

        $client = $portfolio->customerSalesChannel?->user?->getShopifyClient(true);

        if (!$client) {
            return false;
        }

        $query = <<<'QUERY'
        query productOnlyVariant($id: ID!) {
          product(id: $id) {
            variants(first: 2) {
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

        try {
            $response = $client->request($query, ['id' => $productId]);
        } catch (Exception) {
            return false;
        }

        if (!empty($response['errors']) || !isset($response['body'])) {
            return false;
        }

        $body = $response['body']->toArray();

        if (Arr::has($body, 'errors')) {
            return false;
        }

        $variants = Arr::get($body, 'data.product.variants.edges', []);

        if (count($variants) !== 1) {
            return null;
        }

        $variantId  = (string)Arr::get($variants, '0.node.id');
        $variantSku = Str::lower(trim((string)Arr::get($variants, '0.node.sku')));
        $is         = fn (?string $value) => $variantSku !== '' && Str::lower(trim((string)$value)) === $variantSku;

        $siblingOwner = $siblings->first(fn (Portfolio $sibling) => $sibling->platform_product_variant_id === $variantId)
            ?? $siblings->first(fn (Portfolio $sibling) => $is($sibling->item_code));

        if ($is($portfolio->item_code) || (!$siblingOwner && ($portfolio->platform_product_variant_id === $variantId || $is($portfolio->sku)))) {
            return null;
        }

        $owner = $siblingOwner
            ?? $siblings->first(fn (Portfolio $sibling) => $is($sibling->sku))
            ?? $siblings->first();

        return (string)$owner->item_code;
    }

    public static function replacedVariantMessage(string|false $replacedVariantOwner, bool $unlinked = true): string
    {
        if ($replacedVariantOwner === false) {
            return 'Could not check the variants of this Shopify product, nothing was changed';
        }

        return 'This Shopify product has only one variant and it belongs to '.$replacedVariantOwner.'. Adding this product to it would replace that variant, '
            .($unlinked ? 'so this product was unlinked from it. Upload it to give it a listing of its own' : 'so nothing was changed. Upload this product to give it a listing of its own');
    }
}
