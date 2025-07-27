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
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Sentry;

class SaveShopifyProductData extends RetinaAction
{
    use WithActionUpdate;

    public string $jobQueue = 'shopify';
    public int $jobBackoff = 5;


    public function handle(Portfolio $portfolio, array $productData = []): ?array
    {

        $customerSalesChannel = $portfolio->customerSalesChannel;

        /** @var \App\Models\Dropshipping\ShopifyUser $shopifyUser */
        $shopifyUser = $customerSalesChannel->user;

        if(!$shopifyUser) {
            Sentry::captureMessage("No Shopify user found for this customer sales channel");
            return null;
        }


        $client = $shopifyUser->getShopifyClient(true); // Get GraphQL client

        if (!$client) {
            Sentry::captureMessage("Failed to initialize Shopify GraphQL client");

            return null;
        }

        $productID = $portfolio->platform_product_id;

        if (!$productID) {
            Sentry::captureMessage("No Shopify product ID found in portfolio");

            return null;
        }

        $locationID = $shopifyUser->shopify_location_id;

        try {
            // GraphQL query to get product data
            $query = <<<'QUERY'
            query getProduct($id: ID!, $locationId: ID!) {
              product(id: $id) {
                id
                title
                handle
                descriptionHtml
                productType
                vendor
                tags
                options {
                  id
                  name
                  values
                }
                variants(first: 50) {
                  edges {
                    node {
                      id
                      title
                      price
                      compareAtPrice
                      sku
                      barcode
                      inventoryQuantity
                      inventoryItem {
                        id
                        inventoryLevel(locationId: $locationId) {
                          id                        }
                      }
                    }
                  }
                }
                images(first: 20) {
                  edges {
                    node {
                      id
                      src
                      altText
                      width
                      height
                    }
                  }
                }
                collections(first: 10) {
                  edges {
                    node {
                      id
                      title
                    }
                  }
                }
                metafields(first: 10) {
                  edges {
                    node {
                      id
                      namespace
                      key
                      value
                    }
                  }
                }
                onlineStoreUrl
                createdAt
                updatedAt
              }
            }
            QUERY;

            // Prepare variables for the query
            $variables = [
                'id' => $productID,
                'locationId' => $locationID
            ];

            // Merge any additional query parameters
            if (!empty($productData)) {
                $variables = array_merge($variables, $productData);
            }

            // Make the GraphQL request
            $response = $client->request($query, $variables);


            if (!empty($response['errors']) || !isset($response['body'])) {
                $errorMessage = 'Error in API response: '.json_encode($response['errors'] ?? []);
                Sentry::captureMessage("Product data retrieval failed: ".$errorMessage);

                return null;
            }

            $body = $response['body']->toArray();


            $productData = [];


            if (isset($body['data']['product'])) {
                $productData = $body['data']['product'];
            } else {
                Sentry::captureMessage("Product data not found in response B");

            }


            $sku=Arr::get($productData, 'variants.edges.0.node.sku');



            $data = $portfolio->data;
            data_set($data, 'shopify_product', $productData);

            $dataToUpdate=[
                'data' => $data
            ];
            if($sku){
                data_set($dataToUpdate, 'sku', $sku);
            }

            UpdatePortfolio::run($portfolio,  $dataToUpdate);


            // Format the response to match the expected structure
            return $this->formatProductResponse($productData);
        } catch (Exception $e) {
            dd($e);
            Sentry::captureException($e);

            return null;
        }
    }

    public function getCommandSignature(): string
    {
        return 'shopify:get_product {portfolio_id}';
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
        // Extract variants from the edges/node structure
        $variants = [];
        if (isset($product['variants']['edges'])) {
            foreach ($product['variants']['edges'] as $edge) {
                $variant = $edge['node'];



                $variants[] = $variant;
            }
        }

        // Extract images from the edges/node structure
        $images = [];
        if (isset($product['images']['edges'])) {
            foreach ($product['images']['edges'] as $edge) {
                $images[] = $edge['node'];
            }
        }

        // Extract collections from the edges/node structure
        $collections = [];
        if (isset($product['collections']['edges'])) {
            foreach ($product['collections']['edges'] as $edge) {
                $collections[] = $edge['node'];
            }
        }

        $metafields = [];
        if (isset($product['metafields']['edges'])) {
            foreach ($product['metafields']['edges'] as $edge) {
                $metafields[] = $edge['node'];
            }
        }

        // Format the response to match the expected structure
        return [
            'id'               => $product['id'],
            'title'            => $product['title'],
            'handle'           => $product['handle'],
            'body_html'        => $product['descriptionHtml'],
            'vendor'           => $product['vendor'],
            'product_type'     => $product['productType'],
            'tags'             => $product['tags'],
            'options'          => $product['options'] ?? [],
            'variants'         => $variants,
            'images'           => $images,
            'collections'      => $collections,
            'metafields'       => $metafields,
            'online_store_url' => $product['onlineStoreUrl'] ?? null,
            'created_at'       => $product['createdAt'],
            'updated_at'       => $product['updatedAt']
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
            $command->error("Failed to retrieve product data");

            return;
        }

        // Display the product data in a table format
        $command->info("Product Information:");
        $command->table(['Field', 'Value'], [
            ['ID', $result['id']],
            ['Title', $result['title']],
            ['Handle', $result['handle']],
            ['Vendor', $result['vendor']],
            ['Product Type', $result['product_type']],
            ['Tags', is_array($result['tags']) ? implode(', ', $result['tags']) : $result['tags']],
            ['Variants Count', count($result['variants'])],
            ['Images Count', count($result['images'])],
            ['Created At', $result['created_at']],
            ['Updated At', $result['updated_at']]
        ]);

        // Display variants information
        if (!empty($result['variants'])) {
            $command->info("\nVariants Information:");
            $variantData = [];
            foreach ($result['variants'] as $index => $variant) {
                $variantData[] = [
                    'Index'          => $index + 1,
                    'ID'             => $variant['id'],
                    'Title'          => $variant['title'],
                    'Price'          => $variant['price'],
                    'SKU'            => $variant['sku'] ?? 'N/A',
                    'Barcode'        => $variant['barcode'] ?? 'N/A',
                    'Inventory'      => $variant['inventory_quantity'] ?? 'N/A',
                    'InventoryLevel' => isset($variant['inventoryItem']['inventoryLevel']) ?
                                        $variant['inventoryItem']['inventoryLevel']['id'] : 'ERROR'
                ];
            }
            $command->table(['Index', 'ID', 'Title', 'Price', 'SKU', 'Barcode', 'Inventory',  'InventoryLevel'], $variantData);
        }

        $command->info("\nProduct data retrieved successfully");
    }
}
