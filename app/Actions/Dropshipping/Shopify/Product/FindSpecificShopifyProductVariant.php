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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

class FindSpecificShopifyProductVariant
{
    use AsAction;


    public function handle(CustomerSalesChannel $customerSalesChannel, string $variantId): ?array
    {
        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $customerSalesChannel->user;

        if (!$shopifyUser) {
            return null;
        }

        $productId = $this->convertGidToSimpleId($variantId);

        $client = $shopifyUser->getShopifyClient(true); // Get GraphQL client

        if (!$client) {
            return null;
        }

        try {
            // GraphQL query to get a specific product variant by ID
            $query = <<<'QUERY'
            query ProductVariantsList($productId: String!) {
              productVariants(first: 10, query: $productId) {
                edges {
                  node {
                    id
                    title
                    price
                    updatedAt
                    inventoryQuantity
                    product {
                      id
                      title
                    }
                  }
                }
              }
            }
            QUERY;

            $variables = [
                'productId' => $productId
            ];

            // Make the GraphQL request
            $response = $client->request($query, $variables);

            if (!empty($response['errors']) || !isset($response['body'])) {
                $errorMessage = 'Error in API response: '.json_encode($response['errors'] ?? []);
                Log::info("Variant search failed: channel: $customerSalesChannel->id  product ID: $productId  ".$errorMessage);
                return null;
            }

            $body = $response['body']->toArray();

            // Check if variant was found
            if (!isset($body['data']['productVariants']) || empty($body['data']['productVariants'])) {
                return null;
            }

            $variant = Arr::get($body['data']['productVariants'], 'edges.0.node');

            // Return variant data as object
            return [
                'id' => $variant['id'],
                'title' => $variant['title'],
                'price' => $variant['price']
            ];
        } catch (\Exception $e) {
            Log::error("Exception in variant search: ".$e->getMessage());
            return null;
        }
    }

    public function getCommandSignature(): string
    {
        return 'shopify:find-variant {customerSalesChannel} {variantId}';
    }

    public function convertGidToSimpleId(string $gid): string
    {
        // Extract the resource type and ID from GID
        // Format: gid://shopify/{ResourceType}/{ID}
        if (preg_match('#gid://shopify/([^/]+)/(\d+)#', $gid, $matches)) {
            $resourceType = strtolower($matches[1]); // Product, ProductVariant, etc.
            $id = $matches[2];

            // Convert to snake_case format
            $resourceType = preg_replace('/(?<!^)[A-Z]/', '_$0', $resourceType);
            $resourceType = strtolower($resourceType);

            return "{$resourceType}_id:{$id}";
        }

        return $gid; // Return as-is if format doesn't match
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();

        if (!$customerSalesChannel) {
            $command->error("Customer sales channel not found");
            return;
        }

        $shopifyUser = $customerSalesChannel->user;
        $variantId = $command->argument('variantId');

        if (!$shopifyUser) {
            $command->error("Shopify user not found");
            return;
        }

        $variant = $this->handle($customerSalesChannel, $variantId);
        dd($variant);
    }
}
