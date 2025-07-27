<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Jul 2025 17:40:53 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

class FindShopifyProductVariant
{
    use AsAction;


    public function handle(CustomerSalesChannel $customerSalesChannel, string $searchValue, string $searchType = ''): ?array
    {
        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $customerSalesChannel->user;

        if (!$shopifyUser) {
            Sentry::captureMessage("Shopify user not found");
            ;
            return null;
        }

        $client = $shopifyUser->getShopifyClient(true); // Get GraphQL client

        if (!$client) {
            Sentry::captureMessage("Failed to initialize Shopify GraphQL client");
            return null;
        }

        try {
            // GraphQL query to search for products by variant SKU or barcode
            $query = <<<'QUERY'
            query getProductsByVariant($query: String!) {
              products(first: 10, query: $query) {
                edges {
                  node {
                    id
                    title
                    handle
                    descriptionHtml
                    productType
                    vendor
                    variants(first: 10) {
                      edges {
                        node {
                          id
                          title
                          price
                          sku
                          barcode
                          inventoryQuantity
                        }
                      }
                    }
                    images(first: 5) {
                      edges {
                        node {
                          id
                          src
                        }
                      }
                    }
                  }
                }
              }
            }
            QUERY;


            if ($searchType === 'barcode') {
                $searchParam = "variant:barcode:$searchValue";
            } elseif ($searchType === 'sku') {
                $searchParam = "variant:sku:$searchValue";
            } else {
                $searchParam = $searchValue;
            }


            $variables = [
                'query' => $searchParam
            ];


            // Make the GraphQL request
            $response = $client->request($query, $variables);

            if (!empty($response['errors']) || !isset($response['body'])) {
                $errorMessage = 'Error in API response: '.json_encode($response['errors'] ?? []);
                Sentry::captureMessage("Product search failed: ".$errorMessage);

                return null;
            }

            $body = $response['body']->toArray();

            // Check if products were found
            if (!isset($body['data']['products']['edges']) || empty($body['data']['products']['edges'])) {
                return null;
            }



            // Format the response
            $products = [];
            foreach ($body['data']['products']['edges'] as $edge) {
                $product = $edge['node'];

                $products[] = [
                    'id'           => $product['id'],
                    'title'        => $product['title'],
                    'handle'       => $product['handle'],
                    'vendor'       => $product['vendor'],
                    'images'       => array_map(function ($imageEdge) {
                        return $imageEdge['node'];
                    }, $product['images']['edges'] ?? [])
                ];

            }

            return ['products' => $products];
        } catch (\Exception $e) {
            Sentry::captureException($e);

            return null;
        }
    }

    public function getCommandSignature(): string
    {
        return 'shopify:find-variant {customerSalesChannel} {searchValue} {--type=sku : The type of search (sku or barcode)}';
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();
        $shopifyUser          = $customerSalesChannel->user;
        $searchValue          = $command->argument('searchValue');
        $searchType           = $command->option('type');

        if (!in_array($searchType, ['sku', 'barcode'])) {
            $command->error("Invalid search type. Must be 'sku' or 'barcode'");

            return;
        }

        if (!$shopifyUser) {
            $command->error("Shopify user not found");

            return;
        }

        $result = $this->handle($customerSalesChannel, $searchValue, $searchType);

        if (!$result) {
            $command->info("No products found with variant $searchType: $searchValue");

            return;
        }

        $products = $result['products'];
        $command->info("Found ".count($products)." products with variant $searchType: $searchValue");

        foreach ($products as $index => $product) {
            $command->info("\nProduct #".($index + 1).":");
            $command->table(['Field', 'Value'], [
                ['ID', $product['id']],
                ['Title', $product['title']],
                ['Handle', $product['handle']],
                ['Product Type', $product['product_type']],
                ['Vendor', $product['vendor']]
            ]);

            if (!empty($product['variants'])) {
                $command->info("\nMatching Variants:");
                $variantData = [];
                foreach ($product['variants'] as $vIndex => $variant) {
                    $variantData[] = [
                        'Index'     => $vIndex + 1,
                        'ID'        => $variant['id'],
                        'Title'     => $variant['title'],
                        'Price'     => $variant['price'],
                        'SKU'       => $variant['sku'],
                        'Barcode'   => $variant['barcode'] ?? 'N/A',
                        'Inventory' => $variant['inventoryQuantity'] ?? 'N/A'
                    ];
                }
                $command->table(['Index', 'ID', 'Title', 'Price', 'SKU', 'Barcode', 'Inventory'], $variantData);
            }
        }
    }
}
