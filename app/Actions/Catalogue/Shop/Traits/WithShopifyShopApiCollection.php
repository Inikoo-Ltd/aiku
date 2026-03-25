<?php

namespace App\Actions\Catalogue\Shop\Traits;

use Illuminate\Support\Facades\Log;

trait WithShopifyShopApiCollection
{
    protected array $graphqlQueries = [
        'products' => '
            query ($first: Int!) {
                products(first: $first) {
                    edges {
                        node {
                            id
                            title
                            handle
                            description
                            variants(first: 10) {
                                edges {
                                    node {
                                        id
                                        price
                                        sku
                                    }
                                }
                            }
                        }
                    }
                    pageInfo {
                        endCursor
                        hasNextPage
                        hasPreviousPage
                    }
                }
            }
        ',
        'productsWithCursor' => '
            query ($first: Int!, $after: String) {
                products(first: $first, after: $after) {
                    edges {
                        cursor
                        node {
                            id
                            title
                            handle
                            description
                            status
                            variants(first: 10) {
                                edges {
                                    node {
                                        id
                                        price
                                        sku
                                        inventoryQuantity
                                    }
                                }
                            }
                            images(first: 5) {
                                edges {
                                    node {
                                        id
                                        url
                                        altText
                                    }
                                }
                            }
                        }
                    }
                    pageInfo {
                        endCursor
                        hasNextPage
                        hasPreviousPage
                        startCursor
                    }
                }
            }
        ',
        'deleteProduct' => '
            mutation ($id: ID!) {
                productDelete(input: { id: $id }) {
                    deletedProductId
                    userErrors {
                        field
                        message
                    }
                }
            }
        ',
        'deleteProducts' => '
            mutation productBulkDelete($ids: [ID!]!) {
                productBulkDelete(ids: $ids) {
                    job {
                        id
                        done
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        ',
        'orders' => '
            query ($first: Int!) {
                orders(first: $first) {
                    edges {
                        node {
                            id
                            name
                            createdAt
                            displayFinancialStatus
                            displayFulfillmentStatus
                            email
                            phone
                            fulfillmentOrders(first: 10) {
                              edges {
                                node {
                                  id
                                  status
                                  requestStatus
                                }
                              }
                            }
                            totalPriceSet {
                                shopMoney {
                                    amount
                                    currencyCode
                                }
                            }
                            subtotalPriceSet {
                                shopMoney {
                                    amount
                                    currencyCode
                                }
                            }
                            totalTaxSet {
                                shopMoney {
                                    amount
                                    currencyCode
                                }
                            }
                            totalShippingPriceSet {
                                shopMoney {
                                    amount
                                    currencyCode
                                }
                            }
                            customer {
                                id
                                email
                                firstName
                                lastName
                                phone
                                defaultAddress {
                                    address1
                                    address2
                                    city
                                    province
                                    provinceCode
                                    country
                                    countryCodeV2
                                    zip
                                    phone
                                    company
                                    firstName
                                    lastName
                                }
                            }
                            shippingAddress {
                                address1
                                address2
                                city
                                province
                                provinceCode
                                country
                                countryCodeV2
                                zip
                                phone
                                company
                                firstName
                                lastName
                            }
                            billingAddress {
                                address1
                                address2
                                city
                                province
                                provinceCode
                                country
                                countryCodeV2
                                zip
                                phone
                                company
                                firstName
                                lastName
                            }
                            lineItems(first: 50) {
                                edges {
                                    node {
                                        id
                                        name
                                        title
                                        quantity
                                        sku
                                        variant {
                                            id
                                            title
                                            price
                                        }
                                        originalUnitPriceSet {
                                            shopMoney {
                                                amount
                                                currencyCode
                                            }
                                        }
                                        discountedUnitPriceSet {
                                            shopMoney {
                                                amount
                                                currencyCode
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ',
        'shop' => '
            query {
                shop {
                    name
                    primaryDomain {
                        url
                    }
                    billingAddress {
                        address1
                        address2
                        city
                        provinceCode
                        zip
                        countryCodeV2
                    }
                }
            }
        ',
        // Add other GraphQL queries as needed
    ];

    /**
     * Execute a GraphQL query
     *
     * @param string $queryName
     * @param array $variables
     * @return array
     */
    protected function executeGraphQLQuery(string $queryName, array $variables = []): array
    {
        $shopifyClient = $this->getShopifyClient(true);
        $query = $this->graphqlQueries[$queryName] ?? '';

        $response = $shopifyClient->request($query, $variables);

        if (!empty($response['errors']) || !isset($response['body'])) {
            $result = [];
            data_set($result, 'error', true);
            return $result;
        }

        return $response['body']->toArray();
    }

    /**
     * Get products with optional variables
     *
     * @param array $variables
     * @return array
     */
    public function getShopifyProducts(array $variables = ['first' => 10]): array
    {
        return $this->executeGraphQLQuery('products', $variables);
    }

    /**
     * Get all products using cursor-based pagination (batched fetch)
     *
     * Fetches every page automatically and returns a flat array of product nodes.
     *
     * @param int $batchSize Number of products per page (max 250)
     * @return array ['products' => [...nodes], 'total' => int, 'errors' => [...]]
     */
    public function getAllShopifyProducts(int $batchSize = 50): array
    {
        $allProducts = [];
        $after        = null;
        $hasNextPage  = true;
        $errors       = [];

        while ($hasNextPage) {
            $variables = ['first' => min($batchSize, 250)];
            if ($after) {
                $variables['after'] = $after;
            }

            $response = $this->executeGraphQLQuery('productsWithCursor', $variables);

            if (!empty($response['error'])) {
                Log::error('GraphQL request failed: ' . json_encode($response));
                $errors[] = 'Failed to fetch page after cursor: ' . ($after ?? 'start');
                break;
            }

            $edges       = $response['data']['products']['edges'] ?? [];
            $pageInfo    = $response['data']['products']['pageInfo'] ?? [];
            $hasNextPage = $pageInfo['hasNextPage'] ?? false;
            $after       = $pageInfo['endCursor'] ?? null;

            echo count($edges) . "\n";

            foreach ($edges as $edge) {
                $allProducts[] = $edge['node'];
            }
        }

        return [
            'products' => $allProducts,
            'total'    => count($allProducts),
            'errors'   => $errors,
        ];
    }

    /**
     * Delete a single Shopify product by its GID
     *
     * @param string $productId  e.g. "gid://shopify/Product/123456789"
     * @return array ['deleted_id' => string|null, 'success' => bool, 'errors' => array]
     */
    public function deleteShopifyProduct(string $productId): array
    {
        $response = $this->executeGraphQLQuery('deleteProduct', ['id' => $productId]);

        if (!empty($response['error'])) {
            return ['deleted_id' => null, 'success' => false, 'errors' => ['GraphQL request failed']];
        }

        $payload    = $response['data']['productDelete'] ?? [];
        $userErrors = $payload['userErrors'] ?? [];
        $deletedId  = $payload['deletedProductId'] ?? null;

        return [
            'deleted_id' => $deletedId,
            'success'    => empty($userErrors) && $deletedId !== null,
            'errors'     => $userErrors,
        ];
    }

    /**
     * Bulk-delete Shopify products using the productBulkDelete mutation.
     *
     * Shopify's bulk delete is async — it returns a Job ID. Poll the job
     * or use a webhook to confirm completion.
     *
     * @param array $productIds  Array of GIDs, e.g. ["gid://shopify/Product/1", ...]
     * @return array ['job_id' => string|null, 'done' => bool, 'success' => bool, 'errors' => array]
     */
    public function bulkDeleteShopifyProducts(array $productIds): array
    {
        if (empty($productIds)) {
            return ['job_id' => null, 'done' => false, 'success' => false, 'errors' => ['No product IDs provided']];
        }

        $response = $this->executeGraphQLQuery('deleteProducts', ['ids' => $productIds]);

        if (!empty($response['error'])) {
            return ['job_id' => null, 'done' => false, 'success' => false, 'errors' => ['GraphQL request failed']];
        }

        $payload    = $response['data']['productBulkDelete'] ?? [];
        $userErrors = $payload['userErrors'] ?? [];
        $job        = $payload['job'] ?? null;

        Log::info('Bulk delete job: ' . json_encode($job));

        return [
            'job_id'  => $job['id'] ?? null,
            'done'    => $job['done'] ?? false,
            'success' => empty($userErrors),
            'errors'  => $userErrors,
        ];
    }

    /**
     * Delete products in sequential batches to avoid hitting Shopify's rate limits.
     *
     * Unlike bulkDeleteShopifyProducts (single async mutation), this method
     * calls deleteProduct one-by-one in chunks, giving you per-item results.
     *
     * @param array $productIds  Array of GIDs
     * @param int   $chunkSize   Products per batch (default 10; keep low to respect rate limits)
     * @return array ['deleted' => [...], 'failed' => [...], 'total' => int]
     */
    public function batchDeleteShopifyProducts(array $productIds, int $chunkSize = 10): array
    {
        $deleted = [];
        $failed  = [];

        foreach (array_chunk($productIds, $chunkSize) as $chunk) {
            foreach ($chunk as $productId) {
                $result = $this->deleteShopifyProduct($productId);

                if ($result['success']) {
                    $deleted[] = $result['deleted_id'];
                    echo 'Batch delete result OK: ' . $result['deleted_id'] . "\n";
                } else {
                    $failed[] = [
                        'id'     => $productId,
                        'errors' => $result['errors'],
                    ];
                    echo 'Batch delete result NO: ' . $productId . "\n";
                }
            }
        }

        return [
            'deleted' => $deleted,
            'failed'  => $failed,
            'total'   => count($productIds),
        ];
    }

    /**
     * Get orders with optional variables
     *
     * @param array $variables
     * @return array
     */
    public function getShopifyOrders(array $variables = ['first' => 10]): array
    {
        return $this->executeGraphQLQuery('orders', $variables);
    }

    /**
     * Get shop information
     *
     * @return array
     */
    public function getShopifyShop(): array
    {
        return $this->executeGraphQLQuery('shop');
    }
}
