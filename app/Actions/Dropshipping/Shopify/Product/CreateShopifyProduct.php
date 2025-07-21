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
use Lorisleiva\Actions\ActionRequest;
use Sentry;

class CreateShopifyProduct extends RetinaAction
{
    use WithActionUpdate;

    public string $jobQueue = 'shopify';
    public int $jobBackoff = 5;

    /**
     * Create a product in Shopify using GraphQL
     *
     * @param  ShopifyUser  $shopifyUser  The Shopify user account to use for API access
     * @param  Portfolio  $portfolio  The portfolio to upload to Shopify
     * @param  array  $productData  Optional additional data for the product
     *
     * @return array|null The created product data or null if creation failed
     */
    public function handle(ShopifyUser $shopifyUser, Portfolio $portfolio, array $productData = []): ?array
    {
        $client = $shopifyUser->getShopifyClient(true); // Get GraphQL client

        if (!$client) {
            Sentry::captureMessage("Failed to initialize Shopify GraphQL client");

            return null;
        }

        /** @var Product $product */
        $product = $portfolio->item;
        $images  = [];

        // Process images
        foreach ($product->images as $image) {
            $base64Image = null;
            try {
                $base64Image = $image->getBase64Image();
            } catch (Exception) {
                // Skip if image can't be processed
            }

            if ($base64Image) {
                $images[] = [
                    "alt" => $product->name ?? '',
                    "src" => "data:image/jpeg;base64,".$base64Image
                ];
            }
        }

        try {
            // GraphQL mutation to create a product
            $mutation = <<<'MUTATION'
            mutation productCreate($input: ProductInput!) {
              productCreate(input: $input) {
                product {
                  id
                  title
                  handle
                  descriptionHtml
                  vendor
                  productType
                  variants(first: 10) {
                    edges {
                      node {
                        id
                        price
                        sku
                        barcode
                        inventoryQuantity
                        weight
                        weightUnit
                      }
                    }
                  }
                  images(first: 10) {
                    edges {
                      node {
                        id
                        src
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
                'input' => [
                    'title'           => $portfolio->customer_product_name,
                    'handle'          => $portfolio->platform_handle,
                    'descriptionHtml' => $portfolio->customer_description,
                    'vendor'          => $product->shop->name,
                    'productType'     => $product->family?->name,
                    'images'          => $images,
                    'variants'        => [
                        [
                            'price'               => number_format($portfolio->customer_price, 2, '.', ''),
                            'sku'                 => $product->code,
                            'barcode'             => $product->barcode,
                            'inventoryManagement' => 'SHOPIFY',
                            'inventoryPolicy'     => 'DENY', // Don't allow orders when out of stock
                            'weight'              => $product->marketing_weight,
                            'weightUnit'          => 'GRAMS',
                            'cost'                => $product->price,
                        ]
                    ]
                ]
            ];

            // Merge any additional product data
            if (!empty($productData)) {
                $variables['input'] = array_merge($variables['input'], $productData);
            }

            // Make the GraphQL request
            $response = $client->request($mutation, $variables);

            if (!empty($response['errors']) || !isset($response['body'])) {
                $errorMessage = 'Error in API response: '.json_encode($response['errors'] ?? []);
                UpdatePortfolio::run($portfolio, [
                    'errors_response' => [$errorMessage]
                ]);
                Sentry::captureMessage("Product creation failed: ".$errorMessage);

                return null;
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

                return null;
            }

            // Get the created product
            $createdProduct = $body['data']['productCreate']['product'] ?? null;

            if (!$createdProduct) {
                UpdatePortfolio::run($portfolio, [
                    'errors_response' => ['No product data in response']
                ]);
                Sentry::captureMessage("Product creation failed: No product data in response");

                return null;
            }

            $data = $portfolio->data;
            data_set($data, 'shopify_product_debug', $createdProduct);
            UpdatePortfolio::run($portfolio, [
                'platform_product_id' => Arr::get($createdProduct, 'id'),
                'data'                => $data
            ]);


            // Format the response to match the expected structure
            return $this->formatProductResponse($createdProduct);
        } catch (Exception $e) {
            Sentry::captureException($e);
            UpdatePortfolio::run($portfolio, [
                'errors_response' => [$e->getMessage()]
            ]);

            return null;
        }
    }

    /**
     * Format the GraphQL response to match the REST API response structure
     * This ensures compatibility with existing code that expects the REST API format
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
        $portfolio = Portfolio::find($command->argument('portfolio_id'));;

        $customerSalesChannel = $portfolio->customer_sales_channel;
        $shopifyUser = $customerSalesChannel->user;

        $this->handle($shopifyUser,$portfolio);

    }
}
