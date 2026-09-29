<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Jul 2025 08:28:03 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

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

            UpdatePortfolio::run($portfolio, [
                'errors_response' => $this->portfolioErrorResponse($errorMessage)
            ]);

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
     * productVariantsBulkCreate with REMOVE_STANDALONE_VARIANT deletes the only variant of a product that
     * has no options. When another portfolio of the channel is linked to that variant, adding this one
     * would silently take its listing away.
     *
     * @return string|false|null  the product code of the portfolio whose variant would be replaced, false when it could not be checked
     */
    public static function ownerOfStandaloneVariantThatWouldBeReplaced(Portfolio $portfolio, string $productId): string|false|null
    {
        $siblings = Portfolio::where('customer_sales_channel_id', $portfolio->customer_sales_channel_id)
            ->where('id', '!=', $portfolio->id)
            ->where('status', true)
            ->where('platform_product_id', $productId)
            ->whereNotNull('platform_product_variant_id')
            ->pluck('item_code', 'platform_product_variant_id');

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

        $variantIds = Arr::pluck(Arr::get($body, 'data.product.variants.edges', []), 'node.id');

        if (count($variantIds) !== 1) {
            return null;
        }

        return $siblings->get($variantIds[0]);
    }

    public static function replacedVariantMessage(string|false $replacedVariantOwner): string
    {
        return $replacedVariantOwner === false
            ? 'Could not check the variants of this Shopify product, nothing was changed'
            : 'This Shopify product has only one variant and it belongs to '.$replacedVariantOwner.'. Adding this product to it would replace that variant, so nothing was changed. Upload this product as its own listing instead';
    }
}
