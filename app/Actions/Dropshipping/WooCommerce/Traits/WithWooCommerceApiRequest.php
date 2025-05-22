<?php

namespace App\Actions\Dropshipping\WooCommerce\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;
use Carbon\Carbon;

trait WithWooCommerceApiRequest
{
    /**
     * WooCommerce API URL
     *
     * @var string
     */
    protected string $woocommerceApiUrl = '';

    /**
     * WooCommerce API Consumer Key
     *
     * @var string
     */
    protected string $woocommerceConsumerKey = '';

    /**
     * WooCommerce API Consumer Secret
     *
     * @var string
     */
    protected string $woocommerceConsumerSecret = '';

    /**
     * WooCommerce API Version
     *
     * @var string
     */
    protected string $woocommerceApiVersion = 'wc/v3';

    /**
     * Cache duration in minutes
     *
     * @var int
     */
    protected int $cacheDuration = 60;

    /**
     * Initialize the WooCommerce API credentials
     *
     * @return void
     */
    protected function initWooCommerceApi(): void
    {
        $this->woocommerceApiUrl = Arr::get($this->settings, 'credentials.store_url');
        $this->woocommerceConsumerKey = Arr::get($this->settings, 'credentials.consumer_key');
        $this->woocommerceConsumerSecret = Arr::get($this->settings, 'credentials.consumer_secret');
    }

    /**
     * Get WooCommerce API base URL
     *
     * @return string
     */
    protected function getWooCommerceApiUrl(): string
    {
        if (!$this->woocommerceApiUrl) {
            $this->initWooCommerceApi();
        }

        return $this->woocommerceApiUrl . '/wp-json/' . $this->woocommerceApiVersion;
    }

    /**
     * Make API request to WooCommerce
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @param bool $useCache Whether to use cache for GET requests
     *
     * @return array|null Response data
     */
    protected function makeWooCommerceRequest(string $method, string $endpoint, array $params = [], bool $useCache = false): ?array
    {
        if (!$this->woocommerceConsumerKey) {
            $this->initWooCommerceApi();
        }

        $url = $this->getWooCommerceApiUrl() . '/' . $endpoint;
        $cacheKey = 'woocommerce_' . md5($method . $url . serialize($params));

        // Use cache for GET requests if enabled
        if ($method === 'GET' && $useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = Http::withBasicAuth(
                $this->woocommerceConsumerKey,
                $this->woocommerceConsumerSecret
            );

            // Handle different HTTP methods
            $response = match ($method) {
                'GET' => $response->get($url, $params),
                'POST' => $response->post($url, $params),
                'PUT' => $response->put($url, $params),
                'DELETE' => $response->delete($url, $params),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };

            if ($response->successful()) {
                $data = $response->json();

                // Cache GET requests if enabled
                if ($method === 'GET' && $useCache) {
                    Cache::put($cacheKey, $data, Carbon::now()->addMinutes($this->cacheDuration));
                }

                return $data;
            } else {
                Log::error('WooCommerce API Error', [
                    'url' => $url,
                    'method' => $method,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return null;
            }
        } catch (ConnectionException $e) {
            Log::error('WooCommerce API Connection Error', [
                'url' => $url,
                'method' => $method,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Get products from WooCommerce
     *
     * @param array $params Query parameters
     * @param bool $useCache Whether to use cache
     *
     * @return array|null Products data
     */
    public function getWooCommerceProducts(array $params = [], bool $useCache = true): ?array
    {
        return $this->makeWooCommerceRequest('GET', 'products', $params, $useCache);
    }

    /**
     * Get a single product from WooCommerce
     *
     * @param int $productId Product ID
     * @param bool $useCache Whether to use cache
     *
     * @return array|null Product data
     */
    public function getWooCommerceProduct(int $productId, bool $useCache = true): ?array
    {
        return $this->makeWooCommerceRequest('GET', "products/{$productId}", [], $useCache);
    }

    /**
     * Create a product in WooCommerce
     *
     * @param array $productData Product data
     *
     * @return array|null Created product data
     */
    public function createWooCommerceProduct(array $productData): ?array
    {
        return $this->makeWooCommerceRequest('POST', 'products', $productData);
    }

    /**
     * Update a product in WooCommerce
     *
     * @param int $productId Product ID
     * @param array $productData Product data to update
     *
     * @return array|null Updated product data
     */
    public function updateWooCommerceProduct(int $productId, array $productData): ?array
    {
        return $this->makeWooCommerceRequest('PUT', "products/{$productId}", $productData);
    }

    /**
     * Delete a product from WooCommerce
     *
     * @param int $productId Product ID
     * @param bool $force Whether to permanently delete the product
     *
     * @return array|null Response data
     */
    public function deleteWooCommerceProduct(int $productId, bool $force = false): ?array
    {
        return $this->makeWooCommerceRequest('DELETE', "products/{$productId}", [
            'force' => $force,
        ]);
    }

    /**
     * Get customers from WooCommerce
     *
     * @param array $params Query parameters
     * @param bool $useCache Whether to use cache
     *
     * @return array|null Customers data
     */
    public function getWooCommerceCustomers(array $params = [], bool $useCache = false): ?array
    {
        return $this->makeWooCommerceRequest('GET', 'customers', $params, $useCache);
    }

    /**
     * Get a single customer from WooCommerce
     *
     * @param int $customerId Customer ID
     * @param bool $useCache Whether to use cache
     *
     * @return array|null Customer data
     */
    public function getWooCommerceCustomer(int $customerId, bool $useCache = true): ?array
    {
        return $this->makeWooCommerceRequest('GET', "customers/{$customerId}", [], $useCache);
    }

    /**
     * Create a customer in WooCommerce
     *
     * @param array $customerData Customer data
     *
     * @return array|null Created customer data
     */
    public function createWooCommerceCustomer(array $customerData): ?array
    {
        return $this->makeWooCommerceRequest('POST', 'customers', $customerData);
    }

    /**
     * Update a customer in WooCommerce
     *
     * @param int $customerId Customer ID
     * @param array $customerData Customer data to update
     *
     * @return array|null Updated customer data
     */
    public function updateWooCommerceCustomer(int $customerId, array $customerData): ?array
    {
        return $this->makeWooCommerceRequest('PUT', "customers/{$customerId}", $customerData);
    }

    /**
     * Delete a customer from WooCommerce
     *
     * @param int $customerId Customer ID
     * @param bool $force Whether to permanently delete the customer
     *
     * @return array|null Response data
     */
    public function deleteWooCommerceCustomer(int $customerId, bool $force = false): ?array
    {
        return $this->makeWooCommerceRequest('DELETE', "customers/{$customerId}", [
            'force' => $force,
        ]);
    }

    /**
     * Get orders from WooCommerce
     *
     * @param array $params Query parameters
     * @param bool $useCache Whether to use cache
     *
     * @return array|null Orders data
     */
    public function getWooCommerceOrders(array $params = [], bool $useCache = true): ?array
    {
        return $this->makeWooCommerceRequest('GET', 'orders', $params, $useCache);
    }

    /**
     * Get a single order from WooCommerce
     *
     * @param int $orderId Order ID
     * @param bool $useCache Whether to use cache
     *
     * @return array|null Order data
     */
    public function getWooCommerceOrder(int $orderId, bool $useCache = true): ?array
    {
        return $this->makeWooCommerceRequest('GET', "orders/{$orderId}", [], $useCache);
    }

    /**
     * Create an order in WooCommerce
     *
     * @param array $orderData Order data
     *
     * @return array|null Created order data
     */
    public function createWooCommerceOrder(array $orderData): ?array
    {
        return $this->makeWooCommerceRequest('POST', 'orders', $orderData);
    }

    /**
     * Update an order in WooCommerce
     *
     * @param int $orderId Order ID
     * @param array $orderData Order data to update
     *
     * @return array|null Updated order data
     */
    public function updateWooCommerceOrder(int $orderId, array $orderData): ?array
    {
        return $this->makeWooCommerceRequest('PUT', "orders/{$orderId}", $orderData);
    }

    /**
     * Delete an order from WooCommerce
     *
     * @param int $orderId Order ID
     * @param bool $force Whether to permanently delete the order
     *
     * @return array|null Response data
     */
    public function deleteWooCommerceOrder(int $orderId, bool $force = false): ?array
    {
        return $this->makeWooCommerceRequest('DELETE', "orders/{$orderId}", [
            'force' => $force,
        ]);
    }

    /**
     * Update the status of an order in WooCommerce
     *
     * @param int $orderId Order ID
     * @param string $status New order status
     *
     * @return array|null Updated order data
     */
    public function updateWooCommerceOrderStatus(int $orderId, string $status): ?array
    {
        return $this->updateWooCommerceOrder($orderId, [
            'status' => $status
        ]);
    }

    /**
     * Get product categories from WooCommerce
     *
     * @param array $params Query parameters
     * @param bool $useCache Whether to use cache
     *
     * @return array|null Categories data
     */
    public function getWooCommerceProductCategories(array $params = [], bool $useCache = true): ?array
    {
        return $this->makeWooCommerceRequest('GET', 'products/categories', $params, $useCache);
    }

    /**
     * Batch update multiple products in WooCommerce
     *
     * @param array $batch Batch operations data
     *
     * @return array|null Response data
     */
    public function batchUpdateWooCommerceProducts(array $batch): ?array
    {
        return $this->makeWooCommerceRequest('POST', 'products/batch', $batch);
    }

    /**
     * Batch update multiple orders in WooCommerce
     *
     * @param array $batch Batch operations data
     *
     * @return array|null Response data
     */
    public function batchUpdateWooCommerceOrders(array $batch): ?array
    {
        return $this->makeWooCommerceRequest('POST', 'orders/batch', $batch);
    }

    /**
     * Get order notes from WooCommerce
     *
     * @param int $orderId Order ID
     * @param array $params Query parameters
     * @param bool $useCache Whether to use cache
     *
     * @return array|null Order notes data
     */
    public function getWooCommerceOrderNotes(int $orderId, array $params = [], bool $useCache = true): ?array
    {
        return $this->makeWooCommerceRequest('GET', "orders/{$orderId}/notes", $params, $useCache);
    }

    /**
     * Add a note to an order in WooCommerce
     *
     * @param int $orderId Order ID
     * @param string $note Note content
     * @param bool $customerNote Whether the note is visible to the customer
     *
     * @return array|null Created note data
     */
    public function addWooCommerceOrderNote(int $orderId, string $note, bool $customerNote = false): ?array
    {
        return $this->makeWooCommerceRequest('POST', "orders/{$orderId}/notes", [
            'note' => $note,
            'customer_note' => $customerNote
        ]);
    }

    /**
     * Get coupons from WooCommerce
     *
     * @param array $params Query parameters
     * @param bool $useCache Whether to use cache
     *
     * @return array|null Coupons data
     */
    public function getWooCommerceCoupons(array $params = [], bool $useCache = true): ?array
    {
        return $this->makeWooCommerceRequest('GET', 'coupons', $params, $useCache);
    }

    /**
     * Get report data from WooCommerce
     *
     * @param string $reportType Type of report
     * @param array $params Query parameters
     * @param bool $useCache Whether to use cache
     *
     * @return array|null Report data
     */
    public function getWooCommerceReport(string $reportType, array $params = [], bool $useCache = true): ?array
    {
        return $this->makeWooCommerceRequest('GET', "reports/{$reportType}", $params, $useCache);
    }

    /**
     * Register WooCommerce webhooks for order creation and product deletion
     *
     * @return array The created webhook IDs
     */

    public function registerWooCommerceWebhooks(): array
    {
        $createdWebhooks = [];

        // Create webhook for new orders
        $orderWebhook = $this->createWooCommerceWebhook([
            'name' => 'Order created',
            'topic' => 'order.created',
            'delivery_url' => route('webhooks.woo.orders.create', [
                'wooCommerceUser' => $this->id
            ]),
        ]);

        if (!empty($orderWebhook) && isset($orderWebhook['id'])) {
            $createdWebhooks['order_created'] = $orderWebhook['id'];
        }

        // Create webhook for product deletion
        $productDeleteWebhook = $this->createWooCommerceWebhook([
            'name' => 'Product deleted',
            'topic' => 'product.deleted',
            'delivery_url' => route('webhooks.woo.products.delete', [
                'wooCommerceUser' => $this->id
            ]),
        ]);

        if (!empty($productDeleteWebhook) && isset($productDeleteWebhook['id'])) {
            $createdWebhooks['product_deleted'] = $productDeleteWebhook['id'];
        }

        return $createdWebhooks;
    }

    /**
     * Create a single WooCommerce webhook
     *
     * @param array $webhookData The webhook configuration
     *
     * @return array|null The created webhook data or null on failure
     */
    protected function createWooCommerceWebhook(array $webhookData): ?array
    {
        return $this->makeWooCommerceRequest('POST', 'webhooks', $webhookData);
    }

    /**
     * Delete a WooCommerce webhook by ID
     *
     * @param int $webhookId The webhook ID to delete
     *
     * @return array Success status
     */
    public function deleteWooCommerceWebhook(int $webhookId): array
    {
        return $this->makeWooCommerceRequest('DELETE', "webhooks/{$webhookId}", [
            'force' => true
        ]);
    }

    /**
     * List all registered WooCommerce webhooks
     *
     * @return array List of webhooks
     */
    public function listWooCommerceWebhooks(): array
    {
        return $this->makeWooCommerceRequest('GET', 'webhooks');
    }
}
