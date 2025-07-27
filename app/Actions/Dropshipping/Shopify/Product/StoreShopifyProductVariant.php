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


    public function handle(Portfolio $portfolio): ?array
    {
        $customerSalesChannel = $portfolio->customerSalesChannel;

        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $customerSalesChannel->user;

        $client = $shopifyUser->getShopifyClient(true); // Get GraphQL client

        if (!$client) {
            Sentry::captureMessage("Failed to initialize Shopify GraphQL client");

            return null;
        }

        /** @var Product $product */
        $product = $portfolio->item;


        $productID = $portfolio->platform_product_id;


        if (!$productID) {
            Sentry::captureMessage("No Shopify product ID found in portfolio");

            return null;
        }

        if (!CheckIfShopifyProductIDIsValid::run($productID)) {
            return null;
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
                    'price'               => $product->rrp,
                    'barcode'             => $portfolio->barcode,
                    'compareAtPrice'      => $product->rrp,
                    'inventoryItem'       => $inventoryItem,
                    'inventoryQuantities' => [
                        'availableQuantity' => $product->available_quantity,
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

                return null;
            }

            $body = $response['body']->toArray();

            // Check for user errors in the response
            if (!empty($body['data']['productVariantsBulkUpdate']['userErrors'])) {
                $errors       = $body['data']['productVariantsBulkUpdate']['userErrors'];
                $errorMessage = 'User errors: '.json_encode($errors);
                UpdatePortfolio::run($portfolio, [
                    'errors_response' => [$errorMessage]
                ]);
                Sentry::captureMessage("Product variant update failed B: ".$errorMessage);

                return null;
            }

            // Get the updated product
            $updatedProduct = $body['data']['productVariantsBulkUpdate']['product'] ?? null;

            if (!$updatedProduct) {
                UpdatePortfolio::run($portfolio, [
                    'errors_response' => ['No product data in response']
                ]);
                Sentry::captureMessage("Product variant update failed C: No product data in response; variants: ".json_encode($variables)."   debugLog: ".json_encode($response));


                return null;
            }


            SaveShopifyProductData::run($portfolio);

            // Format the response to match the expected structure
            return $this->formatProductResponse($updatedProduct);
        } catch (Exception $e) {
            Sentry::captureException($e);
            UpdatePortfolio::run($portfolio, [
                'errors_response' => [$e->getMessage()]
            ]);

            return null;
        }
    }

    public function getCommandSignature(): string
    {
        return 'shopify:create_product_variant {portfolio_id}';
    }

    /**
     * Format the GraphQL response to match the expected structure
     *
     * @param  array  $product  The product data from GraphQL response
     *
     * @return array The formatted product data
     */
    private function formatProductResponse(array $product): array
    {
        $variants = [];
        if (isset($product['variants']['edges'])) {
            foreach ($product['variants']['edges'] as $edge) {
                $variants[] = $edge['node'];
            }
        }

        return [
            'id'       => $product['id'],
            'title'    => $product['title'],
            'variants' => $variants
        ];
    }

    public function asCommand(Command $command): void
    {
        $portfolio = Portfolio::find($command->argument('portfolio_id'));

        if (!$portfolio) {
            $command->error("Portfolio not found");

            return;
        }

        $result = $this->handle($portfolio);

        if (!$result) {
            $command->error("Failed to update product variant");

            return;
        }

        // Display the updated product information
        $command->info("Product Information:");
        $command->table(['Field', 'Value'], [
            ['ID', $result['id']],
            ['Title', $result['title']],
            ['Variants Count', count($result['variants'])]
        ]);

        // Display variants information
        if (!empty($result['variants'])) {
            $command->info("\nVariants Information:");
            $variantData = [];
            foreach ($result['variants'] as $index => $variant) {
                $variantData[] = [
                    'Index'     => $index + 1,
                    'ID'        => $variant['id'],
                    'Price'     => $variant['price'] ?? 'N/A',
                    'SKU'       => $variant['sku'] ?? 'N/A',
                    'Barcode'   => $variant['barcode'] ?? 'N/A',
                    'Inventory' => $variant['inventoryQuantity'] ?? 'N/A'
                ];
            }
            $command->table(['Index', 'ID', 'Price', 'SKU', 'Barcode', 'Inventory'], $variantData);
        }

        $command->info("\nProduct variant updated successfully");
    }
}
