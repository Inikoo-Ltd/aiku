<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Jul 2025 08:28:03 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Sentry;

class StoreShopifyProduct extends RetinaAction
{
    use WithActionUpdate;

    public string $jobQueue = 'shopify';
    public int $jobBackoff = 5;


    public function handle(Portfolio $portfolio, array $productData = []): array
    {
        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $portfolio->customerSalesChannel->user;

        $client = $shopifyUser->getShopifyClient(true); // Get GraphQL client

        if (!$client) {
            Sentry::captureMessage("Failed to initialize Shopify GraphQL client");

            return [false, 'Failed to initialize Shopify GraphQL client'];;
        }

        /** @var Product $product */
        $product = $portfolio->item;


        $media = [];

        foreach ($product->images as $image) {
            $media[] = [
                'originalSource'   => GetImgProxyUrl::run($image->getImage()->extension('jpg')),
                'mediaContentType' => 'IMAGE'
            ];
        }


        try {
            // GraphQL mutation to create a product
            $mutation = <<<'MUTATION'
            mutation productCreate($product: ProductInput!, $media: [CreateMediaInput!]) {
              productCreate(input: $product, media: $media) {
                product {
                  id
                  title
                  handle
                  descriptionHtml
                  productType
                  vendor
                  options {
                        id
                        name
                        position
                        optionValues {
                            id
                            name
                            hasVariants
                        }
                    }
                  variants(first: 10) {
                    edges {
                      node {
                        id
                        price
                        sku
                        barcode
                        inventoryQuantity
                      }
                    }
                  }
                }
                userErrors {
                  field
                  message
                }
              }
            }
            MUTATION;

            // Prepare variables for the mutation
            $variables = [
                'product' => [
                    'title'           => $product->name,
                    'handle'          => Str::slug($product->name),
                    'descriptionHtml' => $product->description.' '.$product->description_extra,
                    'productType'     => $product->family?->name,
                    'vendor'          => $product->shop->name,
                ],
                'media'   => $media,
            ];

            // Merge any additional product data
            if (!empty($productData)) {
                $variables['product'] = array_merge($variables['product'], $productData);
            }


            // Make the GraphQL request
            $response = $client->request($mutation, $variables);


            if (!empty($response['errors']) || !isset($response['body'])) {
                $errorMessage = 'Error in API response: '.json_encode($response['errors'] ?? []);
                UpdatePortfolio::run($portfolio, [
                    'errors_response' => [$errorMessage]
                ]);
                Sentry::captureMessage("Product creation failed: ".$errorMessage);

                return [false, $errorMessage];
            }

            $body = $response['body']->toArray();

            // Check for user errors in the response
            if (!empty($body['data']['productCreate']['userErrors'])) {
                $errors       = $body['data']['productCreate']['userErrors'];
                $errorMessage = 'User errors: '.json_encode($errors);
                UpdatePortfolio::run($portfolio, [
                    'errors_response' => [$errorMessage]
                ]);
                Sentry::captureMessage("Product creation failed: ".$errorMessage);

                return [false, $errorMessage];
            }

            // Get the created product
            $createdProduct = $body['data']['productCreate']['product'] ?? null;

            if (!$createdProduct) {
                UpdatePortfolio::run($portfolio, [
                    'errors_response' => ['No product data in response']
                ]);
                Sentry::captureMessage("Product creation failed: No product data in response");

                return [false, 'No product data in response'];
            }


            UpdatePortfolio::run($portfolio, [
                'platform_product_id' => Arr::get($createdProduct, 'id'),
            ]);

            StoreShopifyProductVariant::run($portfolio);


            // Extract variant ID if available
            $variantId = null;
            if (isset($createdProduct['variants']['edges'][0]['node']['id'])) {
                $variantId = $createdProduct['variants']['edges'][0]['node']['id'];
            }

            UpdatePortfolio::run($portfolio, [
                'platform_product_variant_id' => $variantId,
            ]);

            SaveShopifyProductData::run($portfolio);

            // Format the response to match the expected structure
            return [true, $this->formatProductResponse($createdProduct)];
        } catch (Exception $e) {
            Sentry::captureException($e);
            UpdatePortfolio::run($portfolio, [
                'errors_response' => [$e->getMessage()]
            ]);

            return [false, $e->getMessage()];
        }
    }


    private function formatProductResponse(array $product): array
    {
        $variants = [];
        if (isset($product['variants']['edges'])) {
            foreach ($product['variants']['edges'] as $edge) {
                $variants[] = $edge['node'];
            }
        }

        $images = [];
        if (isset($product['images']['edges'])) {
            foreach ($product['images']['edges'] as $edge) {
                $images[] = $edge['node'];
            }
        }

        return [
            'id'           => $product['id'],
            'title'        => $product['title'],
            'handle'       => $product['handle'],
            'body_html'    => $product['descriptionHtml'],
            'vendor'       => $product['vendor'],
            'product_type' => $product['productType'],
            'variants'     => $variants,
            'images'       => $images
        ];
    }

    public function getCommandSignature(): string
    {
        return 'shopify:product:create {portfolio_id}';
    }

    public function asCommand(Command $command): void
    {
        $portfolio = Portfolio::find($command->argument('portfolio_id'));

        if (!$portfolio) {
            $command->error("Portfolio not found");

            return;
        }

        $customerSalesChannel = $portfolio->customerSalesChannel;

        if (!$customerSalesChannel) {
            $command->error("Customer sales channel not found for this portfolio");

            return;
        }

        $shopifyUser = $customerSalesChannel->user;

        if (!$shopifyUser) {
            $command->error("Shopify user not found for this customer sales channel");

            return;
        }

        $command->info("Creating product in Shopify for portfolio #$portfolio->id...");

        [$status, $result] = $this->handle($shopifyUser, $portfolio);

        if (!$status) {
            $command->error("Failed to create product in Shopify");
            $command->error("Errors:");
            $command->error(implode("\n", $portfolio->errors_response ?? []));

            return;
        }

        // Display the product data in a table format
        $command->info("Product created successfully!");
        $command->table(['Field', 'Value'], [
            ['ID', $result['id']],
            ['Title', $result['title']],
            ['Handle', $result['handle']],
            ['Vendor', $result['vendor'] ?? 'N/A'],
            ['Product Type', $result['product_type'] ?? 'N/A'],
            ['Variants Count', count($result['variants'] ?? [])],
            ['Images Count', count($result['images'] ?? [])]
        ]);

        // Display variants information if available
        if (!empty($result['variants'])) {
            $command->info("\nVariants Information:");
            $variantData = [];
            foreach ($result['variants'] as $index => $variant) {
                $variantData[] = [
                    'Index'     => $index + 1,
                    'ID'        => $variant['id'] ?? 'N/A',
                    'Price'     => $variant['price'] ?? 'N/A',
                    'SKU'       => $variant['sku'] ?? 'N/A',
                    'Barcode'   => $variant['barcode'] ?? 'N/A',
                    'Inventory' => $variant['inventoryQuantity'] ?? 'N/A'
                ];
            }
            $command->table(['Index', 'ID', 'Price', 'SKU', 'Barcode', 'Inventory'], $variantData);
        }
    }
}
