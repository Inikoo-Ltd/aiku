<?php

namespace App\Actions\Dropshipping\Wix\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

trait WithWixApiRequest
{
    private string $wixApiBaseUrl = 'https://www.wixapis.com';
    private string $redirectUri;
    private string $wixAccessToken;
    private string $wixSiteId;
    private int $timeout = 30;

    public function __construct()
    {
        $this->redirectUri = route('retina.dropshipping.platform.wix.callback');
    }

    /**
     * Initialize Wix API credentials
     */
    public function initWixApi(string $accessToken, string $siteId): void
    {
        $this->wixAccessToken = $accessToken;
        $this->wixSiteId = $siteId;
    }

    /**
     * Get authorization headers for API requests
     */
    private function getAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->wixAccessToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'wix-site-id' => $this->wixSiteId,
        ];
    }

    /**
     * Make HTTP request to Wix API
     */
    private function makeWixRequest(string $method, string $endpoint, array $data = []): array
    {
        try {
            $url = $this->wixApiBaseUrl . $endpoint;

            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getAuthHeaders())
                ->$method($url, $data);

            if ($response->failed()) {
                Log::error('Wix API request failed', [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                throw new Exception('Wix API request failed: ' . $response->status());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Wix API exception', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate authorization URL for OAuth2 flow
     */
    public function getWixAuthorizationUrl(array $permissions = []): string
    {
        $defaultPermissions = [
            'STORES.READ',
            'STORES.WRITE',
            'ORDERS.READ',
            'ORDERS.WRITE',
            'INVENTORY.READ',
            'INVENTORY.WRITE'
        ];

        $permissions = empty($permissions) ? $defaultPermissions : $permissions;

        $params = [
            'client_id' => config('services.wix.client_id'),
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $permissions),
            'state' => csrf_token(),
        ];

        return 'https://www.wix.com/oauth/authorize?' . http_build_query($params);
    }

    /**
     * Authenticate with Wix API using OAuth2
     */
    public function wixAuthenticate(string $code, string $redirectUri): array
    {
        $data = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => config('services.wix.client_id'),
            'client_secret' => config('services.wix.client_secret'),
        ];

        $response = Http::timeout($this->timeout)
            ->asForm()
            ->post('https://www.wix.com/oauth/access', $data);

        if ($response->failed()) {
            throw new Exception('Wix authentication failed: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Refresh access token
     */
    public function wixRefreshToken(string $refreshToken): array
    {
        $data = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => config('services.wix.client_id'),
            'client_secret' => config('services.wix.client_secret'),
        ];

        $response = Http::timeout($this->timeout)
            ->asForm()
            ->post('https://www.wix.com/oauth/access', $data);

        if ($response->failed()) {
            throw new Exception('Wix token refresh failed: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Get store information
     */
    public function getWixStoreInfo(): array
    {
        return $this->makeWixRequest('get', '/stores/v1/stores/info');
    }

    /**
     * Get store properties
     */
    public function getWixStoreProperties(): array
    {
        return $this->makeWixRequest('get', '/stores/v1/stores/properties');
    }

    /**
     * Upload/Create a new product
     */
    public function uploadWixProduct(array $productData): array
    {
        $data = [
            'product' => $this->formatProductData($productData)
        ];

        return $this->makeWixRequest('post', '/stores/v1/products', $data);
    }

    /**
     * Get product by ID
     */
    public function getWixProduct(string $productId): array
    {
        return $this->makeWixRequest('get', "/stores/v1/products/{$productId}");
    }

    /**
     * Get products list with optional filtering
     */
    public function getWixProducts(array $filters = []): array
    {
        $queryParams = $this->buildQueryParams($filters);
        $endpoint = '/stores/v1/products' . ($queryParams ? '?' . $queryParams : '');

        return $this->makeWixRequest('get', $endpoint);
    }

    /**
     * Update a product
     */
    public function updateWixProduct(string $productId, array $productData): array
    {
        $data = [
            'product' => $this->formatProductData($productData)
        ];

        return $this->makeWixRequest('patch', "/stores/v1/products/{$productId}", $data);
    }

    /**
     * Delete a product
     */
    public function deleteWixProduct(string $productId): array
    {
        return $this->makeWixRequest('delete', "/stores/v1/products/{$productId}");
    }

    /**
     * Get orders list
     */
    public function getWixOrders(array $filters = []): array
    {
        $queryParams = $this->buildQueryParams($filters);
        $endpoint = '/stores/v1/orders' . ($queryParams ? '?' . $queryParams : '');

        return $this->makeWixRequest('get', $endpoint);
    }

    /**
     * Get order details by ID
     */
    public function getWixOrderDetails(string $orderId): array
    {
        return $this->makeWixRequest('get', "/stores/v1/orders/{$orderId}");
    }

    /**
     * Update order status
     */
    public function updateWixOrder(string $orderId, array $orderData): array
    {
        return $this->makeWixRequest('patch', "/stores/v1/orders/{$orderId}", $orderData);
    }

    /**
     * Add shipping info to order
     */
    public function addWixOrderShipping(string $orderId, array $shippingData): array
    {
        $data = [
            'fulfillment' => [
                'trackingInfo' => [
                    'trackingNumber' => $shippingData['tracking_number'] ?? null,
                    'shippingProvider' => $shippingData['shipping_provider'] ?? null,
                    'trackingLink' => $shippingData['tracking_link'] ?? null,
                ],
                'lineItems' => $shippingData['line_items'] ?? [],
            ]
        ];

        return $this->makeWixRequest('post', "/stores/v1/orders/{$orderId}/fulfillments", $data);
    }

    /**
     * Update order tracking information
     */
    public function updateWixOrderTracking(string $orderId, string $fulfillmentId, array $trackingData): array
    {
        $data = [
            'trackingInfo' => [
                'trackingNumber' => $trackingData['tracking_number'] ?? null,
                'shippingProvider' => $trackingData['shipping_provider'] ?? null,
                'trackingLink' => $trackingData['tracking_link'] ?? null,
            ]
        ];

        return $this->makeWixRequest('patch', "/stores/v1/orders/{$orderId}/fulfillments/{$fulfillmentId}", $data);
    }

    /**
     * Get order fulfillments
     */
    public function getWixOrderFulfillments(string $orderId): array
    {
        return $this->makeWixRequest('get', "/stores/v1/orders/{$orderId}/fulfillments");
    }

    /**
     * Mark order as fulfilled
     */
    public function markWixOrderFulfilled(string $orderId, array $fulfillmentData): array
    {
        $data = [
            'fulfillment' => [
                'lineItems' => $fulfillmentData['line_items'] ?? [],
                'trackingInfo' => [
                    'trackingNumber' => $fulfillmentData['tracking_number'] ?? null,
                    'shippingProvider' => $fulfillmentData['shipping_provider'] ?? null,
                    'trackingLink' => $fulfillmentData['tracking_link'] ?? null,
                ],
            ]
        ];

        return $this->makeWixRequest('post', "/stores/v1/orders/{$orderId}/fulfillments", $data);
    }

    /**
     * Get order transactions
     */
    public function getWixOrderTransactions(string $orderId): array
    {
        return $this->makeWixRequest('get', "/stores/v1/orders/{$orderId}/transactions");
    }

    /**
     * Cancel order
     */
    public function cancelWixOrder(string $orderId): array
    {
        return $this->makeWixRequest('post', "/stores/v1/orders/{$orderId}/cancel");
    }

    /**
     * Get inventory for a product
     */
    public function getWixProductInventory(string $productId): array
    {
        return $this->makeWixRequest('get', "/stores/v1/products/{$productId}/inventory");
    }

    /**
     * Update product inventory
     */
    public function updateWixProductInventory(string $productId, array $inventoryData): array
    {
        return $this->makeWixRequest('patch', "/stores/v1/products/{$productId}/inventory", $inventoryData);
    }

    /**
     * Get product options (variants)
     */
    public function getWixProductOptions(string $productId): array
    {
        return $this->makeWixRequest('get', "/stores/v1/products/{$productId}/options");
    }

    /**
     * Get product media
     */
    public function getWixProductMedia(string $productId): array
    {
        return $this->makeWixRequest('get', "/stores/v1/products/{$productId}/media");
    }

    /**
     * Upload product media
     */
    public function uploadWixProductMedia(string $productId, array $mediaData): array
    {
        return $this->makeWixRequest('post', "/stores/v1/products/{$productId}/media", $mediaData);
    }

    /**
     * Get categories
     */
    public function getWixCategories(): array
    {
        return $this->makeWixRequest('get', '/stores/v1/categories');
    }

    /**
     * Create category
     */
    public function createWixCategory(array $categoryData): array
    {
        $data = [
            'category' => $categoryData
        ];

        return $this->makeWixRequest('post', '/stores/v1/categories', $data);
    }

    /**
     * Get collections
     */
    public function getWixCollections(): array
    {
        return $this->makeWixRequest('get', '/stores/v1/collections');
    }

    /**
     * Create collection
     */
    public function createWixCollection(array $collectionData): array
    {
        $data = [
            'collection' => $collectionData
        ];

        return $this->makeWixRequest('post', '/stores/v1/collections', $data);
    }

    /**
     * Format product data for API
     */
    private function formatProductData(array $productData): array
    {
        return [
            'name' => $productData['name'] ?? '',
            'description' => $productData['description'] ?? '',
            'sku' => $productData['sku'] ?? null,
            'visible' => $productData['visible'] ?? true,
            'weight' => $productData['weight'] ?? null,
            'productType' => $productData['product_type'] ?? 'physical',
            'priceData' => [
                'price' => $productData['price'] ?? 0,
                'discountedPrice' => $productData['discounted_price'] ?? null,
                'currency' => $productData['currency'] ?? 'USD',
            ],
            'costAndProfitData' => [
                'itemCost' => $productData['item_cost'] ?? null,
                'profit' => $productData['profit'] ?? null,
                'profitMargin' => $productData['profit_margin'] ?? null,
            ],
            'additionalInfoSections' => $productData['additional_info'] ?? [],
            'ribbons' => $productData['ribbons'] ?? [],
            'media' => [
                'mainMedia' => $productData['main_media'] ?? null,
                'mediaItems' => $productData['media_items'] ?? [],
            ],
            'customTextFields' => $productData['custom_text_fields'] ?? [],
            'manageVariants' => $productData['manage_variants'] ?? false,
            'productOptions' => $productData['product_options'] ?? [],
            'productPageUrl' => $productData['product_page_url'] ?? null,
            'numericId' => $productData['numeric_id'] ?? null,
            'inventoryItemId' => $productData['inventory_item_id'] ?? null,
            'discount' => $productData['discount'] ?? null,
            'collectionIds' => $productData['collection_ids'] ?? [],
            'brand' => $productData['brand'] ?? null,
            'ribbon' => $productData['ribbon'] ?? null,
        ];
    }

    /**
     * Build query parameters from filters array
     */
    private function buildQueryParams(array $filters): string
    {
        $params = [];

        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                $params[] = $key . '=' . implode(',', $value);
            } else {
                $params[] = $key . '=' . urlencode($value);
            }
        }

        return implode('&', $params);
    }

    /**
     * Handle webhook verification
     */
    public function verifyWixWebhook(string $payload, string $signature): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, config('services.wix.webhook_secret'));

        return hash_equals($signature, $expectedSignature);
    }

    /**
     * Get webhook events
     */
    public function getWixWebhookEvents(): array
    {
        return [
            'stores/products/created',
            'stores/products/updated',
            'stores/products/deleted',
            'stores/orders/created',
            'stores/orders/updated',
            'stores/orders/paid',
            'stores/orders/fulfilled',
            'stores/orders/canceled',
            'stores/inventory/updated',
        ];
    }

    /**
     * Set API timeout
     */
    public function setWixApiTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    /**
     * Get current API timeout
     */
    public function getWixApiTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Test API connection
     */
    public function testWixConnection(): bool
    {
        try {
            $this->getWixStoreInfo();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
