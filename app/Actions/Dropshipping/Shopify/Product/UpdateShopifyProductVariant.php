<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Jul 2025 08:28:03 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Sentry;

class UpdateShopifyProductVariant extends RetinaAction
{
    use WithActionUpdate;

    public function handle(Portfolio $portfolio): array
    {
        $customerSalesChannel = $portfolio->customerSalesChannel;

        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $customerSalesChannel->user;

        $client = $shopifyUser->getShopifyClient(true);

        if (!$client) {
            Sentry::captureMessage("Failed to initialize Shopify GraphQL client");

            return [false, 'Failed to initialize Shopify GraphQL client'];
        }

        /** @var Product $product */
        $product = $portfolio->item;

        $productID = $portfolio->platform_product_id;

        UpdateShopifyProduct::run($portfolio);

        if (!$productID) {
            Sentry::captureMessage("No Shopify product ID found in portfolio");

            return [false, 'No Shopify product ID found in portfolio'];
        }

        if (!CheckIfShopifyProductIDIsValid::run($productID)) {
            return [false, 'Invalid Shopify product ID'];
        }

        try {
            $variantId      = $portfolio->platform_product_variant_id;

            if (!$variantId) {
                $errorMessage = 'No variant ID found for product: '.$productID;
                UpdatePortfolio::run($portfolio, ['errors_response' => [$errorMessage]]);
                Sentry::captureMessage("Product variant update failed: ".$errorMessage);

                return [false, $errorMessage];
            }

            $mutation = <<<'MUTATION'
            mutation ProductVariantsBulkUpdate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
              productVariantsBulkUpdate(productId: $productId, variants: $variants) {
                productVariants {
                  id
                  price
                  compareAtPrice
                }
                userErrors {
                  field
                  message
                }
              }
            }
            MUTATION;

            if ($portfolio->customer_price > 0 && $portfolio->customer_price !== $product->rrp) {
                $price        = $portfolio->customer_price;
                $comparePrice = $portfolio->customer_price;
            } else {
                $price        = $product->rrp;
                $comparePrice = $product->rrp;
            }

            $variables = [
                'productId' => $productID,
                'variants'  => [
                    [
                        'id'             => $variantId,
                        'price'          => $price,
                        'compareAtPrice' => $comparePrice,
                    ],
                ],
            ];

            $response = $client->request($mutation, $variables);

            if (!empty($response['errors']) || !isset($response['body'])) {
                $errorMessage = 'Error in API response: '.json_encode($response['errors'] ?? []);
                UpdatePortfolio::run($portfolio, ['errors_response' => [$errorMessage]]);
                Sentry::captureMessage("Product variant update failed A: ".$errorMessage);

                return [false, $errorMessage];
            }

            $body = $response['body']->toArray();

            if (!empty($body['data']['productVariantsBulkUpdate']['userErrors'])) {
                $errors       = $body['data']['productVariantsBulkUpdate']['userErrors'];
                $errorMessage = 'User errors: '.json_encode($errors);
                UpdatePortfolio::run($portfolio, ['errors_response' => [$errorMessage]]);
                Sentry::captureMessage("Product variant update failed B: ".$errorMessage);

                return [false, $errorMessage];
            }

            SaveShopifyProductData::run($portfolio);

            return [true, ''];

        } catch (Exception $e) {
            Sentry::captureException($e);
            UpdatePortfolio::run($portfolio, [
                'errors_response' => [$e->getMessage()]
            ]);

            return [false, $e->getMessage()];
        }
    }

    public function getCommandSignature(): string
    {
        return 'shopify:update_product_variant {portfolio_id}';
    }

    public function asCommand(Command $command): void
    {
        $portfolio = Portfolio::find($command->argument('portfolio_id'));

        if (!$portfolio) {
            $command->error("Portfolio not found");

            return;
        }

        list($status, $message) = $this->handle($portfolio);

        if ($status) {
            $command->info("Product variant price updated successfully");
        } else {
            $command->error("Update failed: ".$message);
        }
    }
}
