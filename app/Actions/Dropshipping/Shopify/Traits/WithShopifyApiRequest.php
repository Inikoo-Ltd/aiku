<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 14 Jul 2025 16:06:53 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Traits;

use Exception;
use Illuminate\Support\Facades\Log;
use Sentry;

trait WithShopifyApiRequest
{
    /**
     * Execute GraphQL query
     */
    protected function executeGraphQLQuery(string $query, array $variables = [])
    {
        try {
            $client = $this->getShopifyClient();

            $response = $client->request('POST', 'graphql.json', [
                'json' => [
                    'query' => $query,
                    'variables' => $variables
                ]
            ]);

            if (!empty($response['errors']) || !isset($response['body'])) {
                $errorData = [
                    'datetime' => now(),
                    'data'     => [
                        'latest_error' => [
                            'status'     => $response['status'],
                            'errors'     => $response['body'],
                            'msg'        => 'An error occurred',
                            'msg_detail' => $response['exception']->getMessage()
                        ]
                    ]
                ];

                if ($response['status'] == '404') {
                    $errorData = [
                        'datetime' => now(),
                        'data'     => [
                            'latest_error' => [
                                'status'     => $response['status'],
                                'errors'     => $response['body'],
                                'msg'        => 'Shop '.$this->name.'  not found',
                                'msg_detail' => $response['exception']->getMessage()
                            ]
                        ]
                    ];

                } elseif ($response['status'] == '401') {
                    $errorData = [
                        'datetime' => now(),
                        'data'     => [
                            'latest_error' => [
                                'status'     => $response['status'],
                                'errors'     => $response['body'],
                                'msg'        => 'Shop '.$this->name.'  wrong credentials',
                                'msg_detail' => $response['exception']->getMessage()
                            ]
                        ]
                    ];
                }

                $this->update([
                    'status' => false,
                    'data'   => [
                        'latest_error' => $errorData
                    ]
                ]);

                return null;
            }

            return $response['body']->toArray();
        } catch (Exception $e) {
            Log::error('GraphQL Query Error: ' . $e->getMessage());
            // throw $e;

            Sentry::captureMessage($e->getMessage());
            return null;
        }
    }

    /**
     * Create a new product
     */
    public function createProduct(array $productData)
    {
        $query = <<<'QUERY'
        mutation productCreate($input: ProductInput!) {
            productCreate(input: $input) {
                product {
                    id
                    title
                    handle
                    status
                    productType
                    vendor
                    tags
                    description
                    variants(first: 10) {
                        edges {
                            node {
                                id
                                sku
                                barcode
                                price
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
        QUERY;

        $variables = ['input' => $productData];
        return $this->executeGraphQLQuery($query, $variables);
    }

    /**
     * Update existing product
     */
    public function updateProduct(string $productId, array $productData)
    {
        $query = <<<'QUERY'
        mutation productUpdate($input: ProductInput!) {
            productUpdate(input: $input) {
                product {
                    id
                    title
                    handle
                    status
                    productType
                    vendor
                    tags
                    description
                    variants(first: 10) {
                        edges {
                            node {
                                id
                                sku
                                barcode
                                price
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
        QUERY;

        $productData['id'] = $productId;
        $variables = ['input' => $productData];
        return $this->executeGraphQLQuery($query, $variables);
    }

    /**
     * Check if a product exists by SKU
     */
    public function checkProductExistsBySku(string $sku): bool
    {
        $query = <<<'QUERY'
        query($sku: String!) {
            productVariants(first: 1, query: $sku) {
                edges {
                    node {
                        id
                        sku
                        product {
                            id
                            handle
                            title
                        }
                    }
                }
            }
        }
        QUERY;

        $variables = ['sku' => "sku:$sku"];
        $response = $this->executeGraphQLQuery($query, $variables);

        return !empty($response['data']['productVariants']['edges']);
    }

    /**
     * Check if the product exists by handle
     */
    public function checkProductExistsByHandle(string $handle): bool
    {
        $query = <<<'QUERY'
        query($handle: String!) {
            product(handle: $handle) {
                id
                title
                handle
                status
            }
        }
        QUERY;

        $variables = ['handle' => $handle];
        $response = $this->executeGraphQLQuery($query, $variables);

        return !empty($response['data']['product']);
    }

    /**
     * Check if the product exists by barcode
     */
    public function checkProductExistsByBarcode(string $barcode): bool
    {
        $query = <<<'QUERY'
        query($barcode: String!) {
            productVariants(first: 1, query: $barcode) {
                edges {
                    node {
                        id
                        barcode
                        sku
                        product {
                            id
                            handle
                            title
                        }
                    }
                }
            }
        }
        QUERY;

        $variables = ['barcode' => "barcode:$barcode"];
        $response = $this->executeGraphQLQuery($query, $variables);

        return !empty($response['data']['productVariants']['edges']);
    }

    /**
     * Check for duplicate products by multiple criteria
     */
    public function checkDuplicateProduct(array $criteria): array
    {
        $duplicates = [];

        if (isset($criteria['sku']) && $this->checkProductExistsBySku($criteria['sku'])) {
            $duplicates[] = 'sku';
        }

        if (isset($criteria['handle']) && $this->checkProductExistsByHandle($criteria['handle'])) {
            $duplicates[] = 'handle';
        }

        if (isset($criteria['barcode']) && $this->checkProductExistsByBarcode($criteria['barcode'])) {
            $duplicates[] = 'barcode';
        }

        return $duplicates;
    }

    /**
     * Get product by SKU
     */
    public function getProductBySku(string $sku)
    {
        $query = <<<'QUERY'
        query($sku: String!) {
            productVariants(first: 1, query: $sku) {
                edges {
                    node {
                        id
                        sku
                        barcode
                        price
                        inventoryQuantity
                        inventoryItem {
                            id
                        }
                        product {
                            id
                            title
                            handle
                            description
                            status
                            productType
                            vendor
                            tags
                            images(first: 10) {
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
                }
            }
        }
        QUERY;

        $variables = ['sku' => "sku:$sku"];
        $response = $this->executeGraphQLQuery($query, $variables);

        return $response['data']['productVariants']['edges'][0]['node'] ?? null;
    }

    /**
     * Get product by handle
     */
    public function getProductByHandle(string $handle)
    {
        $query = <<<'QUERY'
        query($handle: String!) {
            product(handle: $handle) {
                id
                title
                handle
                description
                status
                productType
                vendor
                tags
                variants(first: 50) {
                    edges {
                        node {
                            id
                            sku
                            barcode
                            price
                            inventoryQuantity
                            inventoryItem {
                                id
                            }
                        }
                    }
                }
                images(first: 10) {
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
        QUERY;

        $variables = ['handle' => $handle];
        $response = $this->executeGraphQLQuery($query, $variables);

        return $response['data']['product'] ?? null;
    }

    /**
     * Get product by barcode
     */
    public function getProductByBarcode(string $barcode)
    {
        $query = <<<'QUERY'
        query($barcode: String!) {
            productVariants(first: 1, query: $barcode) {
                edges {
                    node {
                        id
                        sku
                        barcode
                        price
                        inventoryQuantity
                        inventoryItem {
                            id
                        }
                        product {
                            id
                            title
                            handle
                            description
                            status
                            productType
                            vendor
                            tags
                            images(first: 10) {
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
                }
            }
        }
        QUERY;

        $variables = ['barcode' => "barcode:$barcode"];
        $response = $this->executeGraphQLQuery($query, $variables);

        return $response['data']['productVariants']['edges'][0]['node'] ?? null;
    }

    /**
     * Update inventory levels
     */
    public function updateInventory(string $inventoryItemId, string $locationId, int $quantity)
    {
        $query = <<<'QUERY'
        mutation inventoryAdjustQuantity($input: InventoryAdjustQuantityInput!) {
            inventoryAdjustQuantity(input: $input) {
                inventoryLevel {
                    id
                    available
                    location {
                        id
                        name
                    }
                }
                userErrors {
                    field
                    message
                }
            }
        }
        QUERY;

        $variables = [
            'input' => [
                'inventoryItemId' => $inventoryItemId,
                'locationId' => $locationId,
                'availableDelta' => $quantity
            ]
        ];

        return $this->executeGraphQLQuery($query, $variables);
    }

    /**
     * Get inventory levels for a product
     */
    public function getInventoryLevels(string $productId)
    {
        $query = <<<'QUERY'
        query($productId: ID!) {
            product(id: $productId) {
                id
                title
                variants(first: 50) {
                    edges {
                        node {
                            id
                            sku
                            barcode
                            inventoryItem {
                                id
                                inventoryLevels(first: 10) {
                                    edges {
                                        node {
                                            id
                                            available
                                            location {
                                                id
                                                name
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        QUERY;

        $variables = ['productId' => $productId];
        return $this->executeGraphQLQuery($query, $variables);
    }

    /**
     * Manage inventory - set specific quantity
     */
    public function setInventoryQuantity(string $inventoryItemId, string $locationId, int $quantity)
    {
        $query = <<<'QUERY'
        mutation inventorySetQuantity($input: InventorySetQuantityInput!) {
            inventorySetQuantity(input: $input) {
                inventoryLevel {
                    id
                    available
                    location {
                        id
                        name
                    }
                }
                userErrors {
                    field
                    message
                }
            }
        }
        QUERY;

        $variables = [
            'input' => [
                'inventoryItemId' => $inventoryItemId,
                'locationId' => $locationId,
                'availableQuantity' => $quantity
            ]
        ];

        return $this->executeGraphQLQuery($query, $variables);
    }

    /**
     * Get orders using GraphQL
     */
    public function getOrders(int $first = 50, string $after = null, array $filters = [])
    {
        $query = <<<'QUERY'
        query($first: Int!, $after: String, $query: String) {
            orders(first: $first, after: $after, query: $query) {
                edges {
                    node {
                        id
                        name
                        email
                        phone
                        createdAt
                        updatedAt
                        processedAt
                        financialStatus
                        fulfillmentStatus
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
                        customer {
                            id
                            firstName
                            lastName
                            email
                            phone
                        }
                        shippingAddress {
                            firstName
                            lastName
                            company
                            address1
                            address2
                            city
                            province
                            zip
                            country
                            phone
                        }
                        billingAddress {
                            firstName
                            lastName
                            company
                            address1
                            address2
                            city
                            province
                            zip
                            country
                            phone
                        }
                        lineItems(first: 50) {
                            edges {
                                node {
                                    id
                                    title
                                    quantity
                                    variant {
                                        id
                                        sku
                                        barcode
                                        price
                                        product {
                                            id
                                            title
                                            handle
                                        }
                                    }
                                    originalUnitPriceSet {
                                        shopMoney {
                                            amount
                                            currencyCode
                                        }
                                    }
                                }
                            }
                        }
                        fulfillments(first: 10) {
                            edges {
                                node {
                                    id
                                    status
                                    trackingCompany
                                    trackingNumbers
                                    createdAt
                                    updatedAt
                                }
                            }
                        }
                    }
                    cursor
                }
                pageInfo {
                    hasNextPage
                    hasPreviousPage
                    startCursor
                    endCursor
                }
            }
        }
        QUERY;

        $variables = [
            'first' => $first,
            'after' => $after,
            'query' => $this->buildOrderQuery($filters)
        ];

        return $this->executeGraphQLQuery($query, $variables);
    }

    /**
     * Get a specific order by ID
     */
    public function getOrder(string $orderId)
    {
        $query = <<<'QUERY'
        query($id: ID!) {
            order(id: $id) {
                id
                name
                email
                phone
                createdAt
                updatedAt
                processedAt
                financialStatus
                fulfillmentStatus
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
                customer {
                    id
                    firstName
                    lastName
                    email
                    phone
                }
                shippingAddress {
                    firstName
                    lastName
                    company
                    address1
                    address2
                    city
                    province
                    zip
                    country
                    phone
                }
                billingAddress {
                    firstName
                    lastName
                    company
                    address1
                    address2
                    city
                    province
                    zip
                    country
                    phone
                }
                lineItems(first: 50) {
                    edges {
                        node {
                            id
                            title
                            quantity
                            variant {
                                id
                                sku
                                barcode
                                price
                                product {
                                    id
                                    title
                                    handle
                                }
                            }
                            originalUnitPriceSet {
                                shopMoney {
                                    amount
                                    currencyCode
                                }
                            }
                        }
                    }
                }
                fulfillments(first: 10) {
                    edges {
                        node {
                            id
                            status
                            trackingCompany
                            trackingNumbers
                            createdAt
                            updatedAt
                        }
                    }
                }
            }
        }
        QUERY;

        $variables = ['id' => $orderId];
        return $this->executeGraphQLQuery($query, $variables);
    }

    /**
     * Build query string for order filters
     */
    private function buildOrderQuery(array $filters): string
    {
        $queryParts = [];

        if (isset($filters['created_at_min'])) {
            $queryParts[] = "created_at:>'{$filters['created_at_min']}'";
        }

        if (isset($filters['created_at_max'])) {
            $queryParts[] = "created_at:<'{$filters['created_at_max']}'";
        }

        if (isset($filters['financial_status'])) {
            $queryParts[] = "financial_status:{$filters['financial_status']}";
        }

        if (isset($filters['fulfillment_status'])) {
            $queryParts[] = "fulfillment_status:{$filters['fulfillment_status']}";
        }

        if (isset($filters['email'])) {
            $queryParts[] = "email:{$filters['email']}";
        }

        if (isset($filters['name'])) {
            $queryParts[] = "name:{$filters['name']}";
        }

        return implode(' AND ', $queryParts);
    }

    /**
     * Get shop information (using your reference)
     */
    public function getShopInfo()
    {
        $query = <<<'QUERY'
        {
            shop {
                id
                name
                email
                url
                myshopifyDomain
                description
                fulfillmentServices{
                    id
                    inventoryManagement
                    location{
                        id
                        name
                        createdAt
                        isActive
                        fulfillsOnlineOrders
                        address{
                            phone
                            address_line_1: address1
                            address_line_2: address2
                            locality: city
                            administrative_area: province
                            postal_code: zip
                            country_code: countryCode
                        }
                    }
                }
                billingAddress {
                    company_name: company
                    address_line_1: address1
                    address_line_2: address2
                    locality: city
                    administrative_area: province
                    postal_code: zip
                    country_code: countryCodeV2
                }
            }
        }
        QUERY;

        return $this->executeGraphQLQuery($query);
    }

    /**
     * Get locations for inventory management
     */
    public function getLocations()
    {
        $query = <<<'QUERY'
        {
            locations(first: 50) {
                edges {
                    node {
                        id
                        name
                        isActive
                        fulfillsOnlineOrders
                        address {
                            address1
                            address2
                            city
                            province
                            zip
                            country
                            phone
                        }
                    }
                }
            }
        }
        QUERY;

        return $this->executeGraphQLQuery($query);
    }

    /**
     * Bulk update inventory across multiple locations
     */
    public function bulkUpdateInventory(array $inventoryUpdates): array
    {
        $results = [];

        foreach ($inventoryUpdates as $update) {
            $result = $this->setInventoryQuantity(
                $update['inventory_item_id'],
                $update['location_id'],
                $update['quantity']
            );

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Create product variant
     */
    public function createProductVariant(string $productId, array $variantData)
    {
        $query = <<<'QUERY'
        mutation productVariantCreate($input: ProductVariantInput!) {
            productVariantCreate(input: $input) {
                productVariant {
                    id
                    sku
                    barcode
                    price
                    inventoryQuantity
                    inventoryItem {
                        id
                    }
                }
                userErrors {
                    field
                    message
                }
            }
        }
        QUERY;

        $variantData['productId'] = $productId;
        $variables = ['input' => $variantData];
        return $this->executeGraphQLQuery($query, $variables);
    }

    /**
     * Update product variant
     */
    public function updateProductVariant(string $variantId, array $variantData)
    {
        $query = <<<'QUERY'
        mutation productVariantUpdate($input: ProductVariantInput!) {
            productVariantUpdate(input: $input) {
                productVariant {
                    id
                    sku
                    barcode
                    price
                    inventoryQuantity
                    inventoryItem {
                        id
                    }
                }
                userErrors {
                    field
                    message
                }
            }
        }
        QUERY;

        $variantData['id'] = $variantId;
        $variables = ['input' => $variantData];
        return $this->executeGraphQLQuery($query, $variables);
    }

    /**
     * Delete product
     */
    public function deleteProduct(string $productId)
    {
        $query = <<<'QUERY'
        mutation productDelete($input: ProductDeleteInput!) {
            productDelete(input: $input) {
                deletedProductId
                userErrors {
                    field
                    message
                }
            }
        }
        QUERY;

        $variables = ['input' => ['id' => $productId]];
        return $this->executeGraphQLQuery($query, $variables);
    }
}
