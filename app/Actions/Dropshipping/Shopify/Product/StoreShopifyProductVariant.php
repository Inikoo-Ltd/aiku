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
use Sentry;

class StoreShopifyProductVariant extends RetinaAction
{
    use WithActionUpdate;

    public string $jobQueue = 'shopify';
    public int $jobBackoff = 5;


    public function handle(Portfolio $portfolio): array
    {
        $customerSalesChannel = $portfolio->customerSalesChannel;

        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $customerSalesChannel->user;

        $client = $shopifyUser->getShopifyClient(true); // Get GraphQL client

        if (!$client) {
            Sentry::captureMessage("Failed to initialize Shopify GraphQL client");

            return [false, 'Failed to initialize Shopify GraphQL client'];
        }

        /** @var Product $product */
        $product = $portfolio->item;


        $productID = $portfolio->platform_product_id;


        if (!$productID) {
            Sentry::captureMessage("No Shopify product ID found in portfolio");

            return [false, 'No Shopify product ID found in portfolio'];
        }

        if (!CheckIfShopifyProductIDIsValid::run($productID)) {
            return [false, 'Invalid Shopify product ID'];
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


            $inventoryItem = [
                'cost' => $product->price,
                'sku'  => $portfolio->sku,

            ];

            if ($product->marketing_weight) {
                $inventoryItem['measurement'] = [
                    'weight' => [
                        'unit'  => 'GRAMS',
                        'value' => $product->marketing_weight

                    ]
                ];
            }


            // Prepare variables for the mutation
            $variants = [
                [
                    // 'price'               => $product->rrp,
                    'barcode'             => $portfolio->barcode,
                    // 'compareAtPrice'      => $product->rrp,
                    'inventoryItem'       => $inventoryItem,
                    'inventoryQuantities' => [
                        'availableQuantity' => $product->available_quantity ?? 0,
                        'locationId'        => $shopifyUser->shopify_location_id
                    ]
                ]
            ];


            $variables = [
                'productId' => $productID,
                'variants'  => $variants,
            ];


            // Make the GraphQL request
            $response = $client->request($mutation, $variables);

            if (!empty($response['errors']) || !isset($response['body'])) {
                $errorMessage = 'Error in API response: '.json_encode($response['errors'] ?? []);
                UpdatePortfolio::run($portfolio, [
                    'errors_response' => [$errorMessage]
                ]);
                Sentry::captureMessage("Product variant update failed A: ".$errorMessage);

                return [false, $errorMessage];
            }

            $body = $response['body']->toArray();

            if (!empty($body['data']['productVariantsBulkCreate']['userErrors'])) {
                $errors       = $body['data']['productVariantsBulkCreate']['userErrors'];
                $errorMessage = 'User errors: '.json_encode($errors);

                UpdatePortfolio::run($portfolio, [
                    'errors_response' => [$errorMessage]
                ]);
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
}
