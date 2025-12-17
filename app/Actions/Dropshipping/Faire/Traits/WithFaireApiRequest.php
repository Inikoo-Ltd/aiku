<?php

namespace App\Actions\Dropshipping\Faire\Traits;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait WithFaireApiRequest
{
    /**
     * Faire API URL
     *
     * @var string
     */
    protected string $faireApiUrl = 'https://www.faire.com/api/v2';
    protected string $faireAuthUrl = 'https://www.faire.com/oauth2/authorize';
    protected string $faireTokenUrl = 'https://www.faire.com/oauth2/token';

    /**
     * Faire API Token
     *
     * @var string|null
     */
    protected string|null $faireApiToken = '';
    protected string $faireAppId = '';
    protected string $faireAppSecret = '';
    protected string $redirectUri = '';
    protected array $scopes = [
        'READ_PRODUCTS',
        'WRITE_PRODUCTS',
        'READ_ORDERS',
        'WRITE_ORDERS',
        'READ_BRAND',
        'READ_RETAILER',
        'READ_INVENTORIES',
        'WRITE_INVENTORIES',
        'READ_SHIPMENTS',
    ];

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
     * Initialize the Faire API credentials
     *
     * @return void
     */
    protected function initFaireApi(): void
    {
        $this->faireApiToken = $this->access_token;
        $this->faireAppId = config('services.faire.app_id');
        $this->faireAppSecret = config('services.faire.app_key');
        $this->redirectUri = 'https://webhook.site/1518cc78-247f-49e7-bd5b-b9f8da42c5bc';
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
     * Get OAuth authorization URL
     *
     * @param string|null $state Random state parameter for security
     *
     * @return string Authorization URL
     */
    public function getFaireAuthorizationUrl(string $state = null): string
    {
        if (!$this->faireAppId) {
            $this->initFaireApi();
        }

        $scopes = $this->scopes;

        $params = [
            'client_id'     => $this->faireAppId,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'state'         => $state,
        ];

        if (!empty($scopes)) {
            $params['scope'] = implode(' ', $scopes);
        }

        return $this->faireAuthUrl . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     *
     * @param string $code Authorization code from callback
     * @param string $redirectUri Same redirect URI used in authorization
     *
     * @return array|null Token data (access_token, refresh_token, expires_in, etc.)
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): ?array
    {
        if (!$this->faireAppId || !$this->faireAppSecret) {
            $this->initFaireApi();
        }

        try {
            $response = Http::timeout($this->timeOut)
                ->asForm()
                ->post($this->faireTokenUrl, [
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'redirect_uri'  => $redirectUri,
                    'client_id'     => $this->faireAppId,
                    'client_secret' => $this->faireAppSecret,
                ]);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Faire OAuth Token Exchange Error', [
                    'status' => $response->status(),
                    'body'   => $response->body()
                ]);

                return [$response->body()];
            }
        } catch (ConnectionException $e) {
            Log::error('Faire OAuth Connection Error', [
                'error' => $e->getMessage()
            ]);

            return [
                ['message' => 'Faire OAuth Connection Error: ' . $e->getMessage()],
            ];
        }
    }

    /**
     * Refresh access token using refresh token
     *
     * @param string $refreshToken Refresh token
     *
     * @return array|null New token data
     */
    public function refreshAccessToken(string $refreshToken): ?array
    {
        if (!$this->faireAppId || !$this->faireAppSecret) {
            $this->initFaireApi();
        }

        try {
            $response = Http::timeout($this->timeOut)
                ->asForm()
                ->post($this->faireTokenUrl, [
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'client_id'     => $this->faireAppId,
                    'client_secret' => $this->faireAppSecret,
                ]);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Faire Token Refresh Error', [
                    'status' => $response->status(),
                    'body'   => $response->body()
                ]);

                return [$response->body()];
            }
        } catch (ConnectionException $e) {
            Log::error('Faire Token Refresh Connection Error', [
                'error' => $e->getMessage()
            ]);

            return [
                ['message' => 'Faire Token Refresh Error: ' . $e->getMessage()],
            ];
        }
    }

    /**
     * Revoke access token
     *
     * @param string $token Token to revoke
     *
     * @return bool Success status
     */
    public function revokeToken(string $token): bool
    {
        if (!$this->faireAppId || !$this->faireAppSecret) {
            $this->initFaireApi();
        }

        try {
            $response = Http::timeout($this->timeOut)
                ->asForm()
                ->post($this->faireTokenUrl . '/revoke', [
                    'token'         => $token,
                    'client_id'     => $this->faireAppId,
                    'client_secret' => $this->faireAppSecret,
                ]);

            if ($response->successful()) {
                return true;
            } else {
                Log::error('Faire Token Revocation Error', [
                    'status' => $response->status(),
                    'body'   => $response->body()
                ]);

                return false;
            }
        } catch (ConnectionException $e) {
            Log::error('Faire Token Revocation Connection Error', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
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
