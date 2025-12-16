<?php

namespace App\Actions\Dropshipping\Faire\Traits;

trait WithFaireApiRequest
{
    /**
     * Faire API URL
     *
     * @var string
     */
    protected string $faireApiUrl = 'https://www.faire.com/api/v2';

    /**
     * Faire API Token
     *
     * @var string
     */
    protected string $faireApiToken = '';

    /**
     * Cache duration in minutes
     *
     * @var int
     */
    protected int $cacheDuration = 60;

    /**
     * Request timeout in seconds
     *
     * @var int
     */
    public int $timeOut = 30;

    /**
     * Set request timeout
     *
     * @param int $timeOut
     * @return void
     */
    public function setTimeout(int $timeOut): void
    {
        $this->timeOut = $timeOut;
    }

    /**
     * Normalize error message from API response
     *
     * @param array $errorData
     * @return array|null
     */
    public function normalizeErrorMessage(array $errorData): ?array
    {
        // If empty array, no error
        if (empty($errorData)) {
            return null;
        }

        // Check if it's an indexed array (has numeric keys starting from 0)
        $firstError = $errorData[0] ?? $errorData;

        // Case 1: Already has 'message' key (array format)
        if (is_array($firstError) && isset($firstError['message'])) {
            return ['message' => $firstError['message']];
        }

        // Case 2: Has 'code' and 'message' keys
        if (is_array($firstError) && isset($firstError['code'], $firstError['message'])) {
            return ['message' => $firstError['message']];
        }

        // Case 3: Has only 'code' key
        if (is_array($firstError) && isset($firstError['code'])) {
            return ['message' => 'Error: ' . $firstError['code']];
        }

        // Case 4: String error (might be JSON string or plain text)
        if (is_string($firstError)) {
            // Try to decode if it's JSON
            $decoded = json_decode($firstError, true);

            // If it decoded successfully and has a message key
            if (is_array($decoded) && isset($decoded['message'])) {
                return ['message' => $decoded['message']];
            }

            // If it has code and message
            if (is_array($decoded) && isset($decoded['code'], $decoded['message'])) {
                return ['message' => $decoded['message']];
            }

            // Check if it's HTML
            if (str_starts_with(trim($firstError), '<!DOCTYPE') || str_starts_with(trim($firstError), '<')) {
                return ['message' => 'Server returned HTML error page'];
            }

            // Plain text error
            return ['message' => trim($firstError)];
        }

        // Default fallback
        return ['message' => 'Unknown error occurred'];
    }

    /**
     * Initialize the Faire API credentials
     *
     * @return void
     */
    protected function initFaireApi(): void
    {
        $this->faireApiToken = $this->api_token;
    }

    /**
     * Get Faire API base URL
     *
     * @return string
     */
    protected function getFaireApiUrl(): string
    {
        return rtrim($this->faireApiUrl, '/');
    }

    /**
     * Make API request to Faire
     *
     * @param string $method HTTP method (GET, POST, PUT, PATCH, DELETE)
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @param bool $useCache Whether to use cache for GET requests
     *
     * @return array|null Response data
     */
    protected function makeFaireRequest(string $method, string $endpoint, array $params = [], bool $useCache = false): ?array
    {
        if (!$this->faireApiToken) {
            $this->initFaireApi();
        }

        $url      = $this->getFaireApiUrl() . '/' . ltrim($endpoint, '/');
        $cacheKey = 'faire_' . md5($method . $url . serialize($params));

        // Use cache for GET requests if enabled
        if ($method === 'GET' && $useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = Http::timeout($this->timeOut)
                ->withHeaders([
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                    'X-FAIRE-ACCESS-TOKEN' => $this->faireApiToken
                ])
                ->connectTimeout($this->timeOut);

            // Handle different HTTP methods
            $response = match ($method) {
                'GET' => $response->get($url, $params),
                'POST' => $response->post($url, $params),
                'PUT' => $response->put($url, $params),
                'PATCH' => $response->patch($url, $params),
                'DELETE' => $response->delete($url, $params),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: $method"),
            };

            if ($response->successful()) {
                $data = $response->json();

                // Cache GET requests if enabled
                if ($method === 'GET' && $useCache) {
                    Cache::put($cacheKey, $data, Carbon::now()->addMinutes($this->cacheDuration));
                }

                return $data;
            } else {
                Log::error('Faire API Error, status' . $response->status() . ' body:' . $response->body());

                return [$response->body()];
            }
        } catch (ConnectionException $e) {
            Log::error('Faire API Connection Error', [
                'url'    => $url,
                'method' => $method,
                'error'  => $e->getMessage()
            ]);

            // Sentry::captureMessage($e->getMessage());

            return [
                ['message' => 'Faire API Connection Error: ' . $e->getMessage()],
            ];
        }
    }

    /**
     * Get products from Faire
     *
     * @param array $params Query parameters (limit, page, etc.)
     * @param bool $useCache Whether to use cache
     *
     * @return array|null Products data
     */
    public function getFaireProducts(array $params = [], bool $useCache = false): ?array
    {
        return $this->makeFaireRequest('GET', 'products', $params, $useCache);
    }

    /**
     * Get a single product from Faire
     *
     * @param string $productId Product ID
     * @param bool $useCache Whether to use cache
     *
     * @return array|null Product data
     */
    public function getFaireProduct(string $productId, bool $useCache = false): ?array
    {
        return $this->makeFaireRequest('GET', "products/{$productId}", [], $useCache);
    }

    /**
     * Get orders from Faire
     *
     * @param array $params Query parameters (limit, page, state, etc.)
     * @param bool $useCache Whether to use cache
     *
     * @return array|null Orders data
     */
    public function getFaireOrders(array $params = [], bool $useCache = false): ?array
    {
        return $this->makeFaireRequest('GET', 'orders', $params, $useCache);
    }

    /**
     * Get a single order from Faire
     *
     * @param string $orderId Order ID
     * @param bool $useCache Whether to use cache
     *
     * @return array|null Order data
     */
    public function getFaireOrder(string $orderId, bool $useCache = false): ?array
    {
        return $this->makeFaireRequest('GET', "orders/{$orderId}", [], $useCache);
    }

    /**
     * Update order state (e.g., mark as processing, shipped)
     *
     * @param string $orderId Order ID
     * @param array $data Update data
     *
     * @return array|null Response data
     */
    public function updateFaireOrderState(string $orderId, array $data): ?array
    {
        return $this->makeFaireRequest('PATCH', "orders/{$orderId}", $data);
    }

    /**
     * Get inventory for products
     *
     * @param array $params Query parameters
     * @param bool $useCache Whether to use cache
     *
     * @return array|null Inventory data
     */
    public function getFaireInventory(array $params = [], bool $useCache = false): ?array
    {
        return $this->makeFaireRequest('GET', 'inventory', $params, $useCache);
    }

    /**
     * Update inventory levels
     *
     * @param array $inventoryData Inventory update data
     *
     * @return array|null Response data
     */
    public function updateFaireInventory(array $inventoryData): ?array
    {
        return $this->makeFaireRequest('PATCH', 'inventory', $inventoryData);
    }

    /**
     * Get shipments
     *
     * @param array $params Query parameters
     * @param bool $useCache Whether to use cache
     *
     * @return array|null Shipments data
     */
    public function getFaireShipments(array $params = [], bool $useCache = false): ?array
    {
        return $this->makeFaireRequest('GET', 'shipments', $params, $useCache);
    }

    /**
     * Create a shipment
     *
     * @param array $shipmentData Shipment data
     *
     * @return array|null Response data
     */
    public function createFaireShipment(array $shipmentData): ?array
    {
        return $this->makeFaireRequest('POST', 'shipments', $shipmentData);
    }

    /**
     * Get brand information
     *
     * @param bool $useCache Whether to use cache
     *
     * @return array|null Brand data
     */
    public function getFaireBrand(bool $useCache = false): ?array
    {
        return $this->makeFaireRequest('GET', 'brand', [], $useCache);
    }

    /**
     * Update brand information
     *
     * @param array $brandData Brand update data
     *
     * @return array|null Response data
     */
    public function updateFaireBrand(array $brandData): ?array
    {
        return $this->makeFaireRequest('PATCH', 'brand', $brandData);
    }
}
