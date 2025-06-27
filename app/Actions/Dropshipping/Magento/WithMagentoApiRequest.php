<?php

namespace App\Actions\Dropshipping\Magento;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait WithMagentoApiRequest
{
    /**
     * Get Magento API configuration
     */
    protected function getMagentoConfig(): array
    {
        return [
            'base_url' => Arr::get($this->settings, 'credentials.base_url'),
            'token' => Arr::get($this->settings, 'credentials.access_token')
        ];
    }

    /**
     * Get or generate access token for Magento API
     */
    public function getMagentoToken(): string
    {
        $config = $this->getMagentoConfig();

        if ($config['token']) {
            return $config['token'];
        }

        try {
            $response = Http::post($config['base_url'] . '/rest/V1/integration/admin/token', [
                'username' => $this->username,
                'password' => $this->password
            ]);

            if ($response->successful()) {
                $token = $response->json();

                if ($this->exists && $this->getTable()) {
                    $this->update(['settings' => [
                        'credentials' => [
                            ...Arr::get($this->settings, 'credentials'),
                            'access_token' => $token
                        ]
                    ]]);
                }

                return $token;
            }

            throw new Exception('Failed to get Magento token: ' . $response->body());
        } catch (Exception $e) {
            Log::error('Magento token generation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function validateMagentoAccount(string $username, string $password, string $baseUrl): bool
    {
        $response = Http::post($baseUrl . '/rest/V1/integration/admin/token', [
            'username' => $username,
            'password' => $password
        ]);

        if ($response->successful()) {
            $token = $response->json();

            return (bool) $token;
        }

        return false;
    }

    /**
     * Make authenticated request to Magento API
     */
    protected function magentoApiRequest(string $method, string $endpoint, array $data = []): array
    {
        $config = $this->getMagentoConfig();
        $token = $this->getMagentoToken();

        $url = rtrim($config['base_url'], '/') . '/rest/V1/' . ltrim($endpoint, '/');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->$method($url, $data);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            throw new Exception("Magento API request failed: {$response->status()} - {$response->body()}");
        } catch (Exception $e) {
            Log::error("Magento API {$method} request to {$endpoint} failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Upload/Create a product in Magento
     */
    public function uploadProduct(array $productData): array
    {
        // Ensure required product structure
        $product = [
            'product' => [
                'sku' => $productData['sku'],
                'name' => $productData['name'],
                'attribute_set_id' => $productData['attribute_set_id'] ?? 4,
                'price' => $productData['price'],
                'status' => $productData['status'] ?? 1, // 1 = enabled
                'visibility' => $productData['visibility'] ?? 4, // 4 = catalog & search
                'type_id' => $productData['type_id'] ?? 'simple',
                'weight' => $productData['weight'] ?? 1,
                'extension_attributes' => $productData['extension_attributes'] ?? [],
                'custom_attributes' => $productData['custom_attributes'] ?? [],
            ]
        ];

        return $this->magentoApiRequest('post', 'products', $product);
    }

    /**
     * Get products from Magento with optional filters
     */
    public function getProducts(array $searchCriteria = []): array
    {
        $endpoint = 'products';

        if (!empty($searchCriteria)) {
            $params = http_build_query(['searchCriteria' => $searchCriteria]);
            $endpoint .= '?' . $params;
        }

        return $this->magentoApiRequest('get', $endpoint);
    }

    /**
     * Get a single product by SKU
     */
    public function getProduct(string $sku): array
    {
        return $this->magentoApiRequest('get', "products/{$sku}");
    }

    /**
     * Update a product in Magento
     */
    public function updateProduct(string $sku, array $productData): array
    {
        $product = [
            'product' => $productData
        ];

        return $this->magentoApiRequest('put', "products/{$sku}", $product);
    }

    /**
     * Delete a product in Magento
     */
    public function deleteProduct(string $sku): bool
    {
        try {
            $this->magentoApiRequest('delete', "products/{$sku}");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get orders from Magento with optional filters
     */
    public function getOrders(array $searchCriteria = []): array
    {
        $endpoint = 'orders';

        if (!empty($searchCriteria)) {
            $params = http_build_query(['searchCriteria' => $searchCriteria]);
            $endpoint .= '?' . $params;
        }

        return $this->magentoApiRequest('get', $endpoint);
    }

    /**
     * Get a single order by ID
     */
    public function getOrder(int $orderId): array
    {
        return $this->magentoApiRequest('get', "orders/{$orderId}");
    }

    /**
     * Get customers from Magento with optional filters
     */
    public function getCustomers(array $searchCriteria = []): array
    {
        $endpoint = 'customers/search';

        if (!empty($searchCriteria)) {
            $params = http_build_query(['searchCriteria' => $searchCriteria]);
            $endpoint .= '?' . $params;
        }

        return $this->magentoApiRequest('get', $endpoint);
    }

    /**
     * Get a single customer by ID
     */
    public function getCustomer(int $customerId): array
    {
        return $this->magentoApiRequest('get', "customers/{$customerId}");
    }

    /**
     * Handle webhook for product deletion
     * This method should be called from your webhook controller
     */
    public function handleProductDeleteWebhook(array $webhookData): bool
    {
        try {
            $sku = $webhookData['sku'] ?? null;

            if (!$sku) {
                Log::warning('Product delete webhook received without SKU');
                return false;
            }

            // Log the deletion
            Log::info("Product deleted via webhook: SKU {$sku}");

            // You can add custom logic here, such as:
            // - Update local database
            // - Send notifications
            // - Sync with other systems

            // Example: Fire a Laravel event
            // event(new ProductDeletedEvent($sku, $webhookData));

            return true;
        } catch (Exception $e) {
            Log::error('Failed to handle product delete webhook: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle webhook for new/updated orders
     * This method should be called from your webhook controller
     */
    public function handleOrderWebhook(array $webhookData): bool
    {
        try {
            $orderId = $webhookData['entity_id'] ?? $webhookData['order_id'] ?? null;

            if (!$orderId) {
                Log::warning('Order webhook received without order ID');
                return false;
            }

            // Log the order event
            Log::info("Order webhook received: Order ID {$orderId}");

            // Optionally fetch full order details
            try {
                $orderDetails = $this->getOrder($orderId);

                // You can add custom logic here, such as:
                // - Update local database
                // - Process order fulfillment
                // - Send customer notifications
                // - Update inventory

                // Example: Fire a Laravel event
                // event(new OrderWebhookEvent($orderId, $orderDetails, $webhookData));

            } catch (Exception $e) {
                Log::warning("Could not fetch order details for webhook: {$e->getMessage()}");
            }

            return true;
        } catch (Exception $e) {
            Log::error('Failed to handle order webhook: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Set up webhook in Magento (if you have admin access)
     * Note: This requires appropriate permissions and may need to be done manually
     */
    public function createWebhook(string $name, string $endpoint, array $topics): array
    {
        $webhook = [
            'webhook' => [
                'name' => $name,
                'endpoint_url' => $endpoint,
                'topics' => $topics, // e.g., ['sales/order/save_after', 'catalog/product/delete_after']
                'status' => 1, // active
            ]
        ];

        return $this->magentoApiRequest('post', 'webhooks', $webhook);
    }

    /**
     * Batch operations helper
     */
    public function batchProductUpload(array $products): array
    {
        $results = [];

        foreach ($products as $index => $productData) {
            try {
                $result = $this->uploadProduct($productData);
                $results[] = [
                    'index' => $index,
                    'sku' => $productData['sku'],
                    'success' => true,
                    'data' => $result
                ];
            } catch (Exception $e) {
                $results[] = [
                    'index' => $index,
                    'sku' => $productData['sku'] ?? 'unknown',
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Get product inventory/stock information
     */
    public function getProductStock(string $sku): array
    {
        return $this->magentoApiRequest('get', "stockItems/{$sku}");
    }

    /**
     * Update product stock
     */
    public function updateProductStock(string $sku, array $stockData): array
    {
        $stock = [
            'stockItem' => array_merge([
                'item_id' => $stockData['item_id'] ?? null,
                'product_id' => $stockData['product_id'] ?? null,
                'stock_id' => $stockData['stock_id'] ?? 1,
                'qty' => $stockData['qty'],
                'is_in_stock' => $stockData['is_in_stock'] ?? true,
            ], $stockData)
        ];

        return $this->magentoApiRequest('put', "products/{$sku}/stockItems/{$stock['stockItem']['item_id']}", $stock);
    }
}
