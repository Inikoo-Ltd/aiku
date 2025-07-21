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
    protected function magentoApiRequest(string $method, string $endpoint, array $data = [], $queryParams = []): array
    {
        $config = $this->getMagentoConfig();
        $token = $this->getMagentoConfig()['token'];

        $url = rtrim($config['base_url'], '/') . '/rest/V1/' . ltrim($endpoint, '/');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->withQueryParameters($queryParams)->$method($url, $data);

            if ($response->successful()) {
                return $response->json() ? is_array($response->json()) ? $response->json() : [$response->json()] : [];
            } else {
                dd($response->json());
            }

            if ($response->status() === 401) {
                $token = $this->getMagentoToken();

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ])->withQueryParameters($queryParams)->$method($url, $data);

                if ($response->successful()) {
                    return $response->json() ? is_array($response->json()) ? $response->json() : [$response->json()] : [];
                }
            }

            throw new Exception("Magento API request failed: {$response->status()} - {$response->body()}");
        } catch (Exception $e) {
            Log::error("Magento API $method request to $endpoint failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Upload/Create a product in Magento
     */
    public function uploadProduct(array $productData): array
    {
        $product = [
            'product' => $productData
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

        return $this->magentoApiRequest('get', $endpoint, [], $searchCriteria);
    }



    /**
     * Get a single order by ID
     */
    public function getOrder(int $orderId): array
    {
        return $this->magentoApiRequest('get', "orders/{$orderId}");
    }

    /**
     * Update order status and add tracking information
     */
    public function updateOrderStatus(int $orderId, string $status, array $trackingData): array
    {
        $orderData = [
            'entity' => [
                'entity_id' => $orderId,
                'status' => $status
            ]
        ];

        $result = $this->magentoApiRequest('post', "orders", $orderData);

        if (! blank($trackingData) && $status === 'complete') {
            $this->createShipment($orderId, $trackingData);
        }

        return $result;
    }

    /**
     * Create shipment for order
     */
    public function createShipment(int $orderId, array $shipmentData): array
    {
        return $this->magentoApiRequest('post', "order/{$orderId}/ship", [
            'tracks' => $shipmentData
        ]);
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
            Log::info("Product deleted via webhook: SKU $sku");

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
            Log::info("Order webhook received: Order ID $orderId");

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
                Log::warning("Could not fetch order details for webhook: " . $e->getMessage());
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

    public function createBulkWebhook(int $magentoUserId): array
    {
        $webhookTopics = [
            [
                'name' => 'Product Delete Webhook',
                'topics' => ['catalog/product/delete_after'],
                'endpoint' => route('webhooks.magento.products.delete', $magentoUserId)
            ],
            [
                'name' => 'Order Create Webhook',
                'topics' => ['sales/order/save_after'],
                'endpoint' => route('webhooks.magento.orders.catch', $magentoUserId)
            ]
        ];

        $results = [];
        foreach ($webhookTopics as $webhook) {
            try {
                $results[] = $this->createWebhook(
                    $webhook['name'],
                    $webhook['endpoint'],
                    $webhook['topics']
                );
            } catch (Exception $e) {
                Log::error("Failed to create webhook " . $webhook['name'] . ": " . $e->getMessage());
                throw $e;
            }
        }

        return $results;
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

    public function getStores(): array
    {
        try {
            return $this->magentoApiRequest('get', 'store/storeViews');
        } catch (Exception $e) {
            Log::warning('Store views endpoint failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getSources(): array
    {
        return $this->magentoApiRequest('get', 'inventory/sources');
    }

    /**
     * Create inventory source in Magento
     *
     * @param array $sourceData Required data structure:
     * [
     *     'name' => 'string',         // Source name
     *     'source_code' => 'string',  // Unique source code
     *     'enabled' => bool,          // Source status
     *     'description' => 'string',  // Optional description
     *     'latitude' => float,        // Optional latitude
     *     'longitude' => float,       // Optional longitude
     *     'country_id' => 'string',   // Country code
     *     'region_id' => int,        // Region/state ID
     *     'region' => 'string',      // Region/state name
     *     'city' => 'string',        // City name
     *     'street' => 'string',      // Street address
     *     'postcode' => 'string'     // Postal code
     * ]
     */
    public function createSource(array $sourceData): array
    {
        return $this->magentoApiRequest('post', 'inventory/sources', [
            'source' => $sourceData
        ]);
    }

    public function getStocks(): array
    {
        return $this->magentoApiRequest('get', 'inventory/stocks');
    }

    /**
     * Create inventory stock in Magento
     *
     * @param array $stockData Required data structure:
     * [
     *     'name' => 'string',           // Stock name
     *     'stock_id' => int,           // Optional stock ID
     *     'extension_attributes' => [   // Optional extension attributes
     *         'sales_channels' => [     // Sales channels assigned to stock
     *             [
     *                 'type' => 'string',  // Channel type (website/store/store_group)
     *                 'code' => 'string'   // Channel code
     *             ]
     *         ]
     *     ]
     * ]
     */
    public function createStock(array $stockData): array
    {
        return $this->magentoApiRequest('post', 'inventory/stocks', [
            'stock' => $stockData
        ]);
    }

    /**
     * Assign inventory sources to a stock
     *
     * @param int $stockId Stock ID to assign sources to
     * @param array $sources Array of source assignments in format:
     * [
     *     [
     *         'source_code' => 'string',  // Unique source identification code
     *         'position' => int,          // Priority position of the source
     *         'status' => int             // Source status (1 for enabled, 0 for disabled)
     *     ],
     *     ...
     * ]
     *
     * @return array Response from Magento API
     */
    public function assignSourceToStock(array $sources): array
    {
        return $this->magentoApiRequest('post', "inventory/stock-source-links", [
            'links' => $sources
        ]);
    }

    public function updateSourceItemsBySku(array $sourceItems): array
    {
        return $this->magentoApiRequest('post', 'inventory/source-items', [
            'sourceItems' => $sourceItems
        ]);
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
