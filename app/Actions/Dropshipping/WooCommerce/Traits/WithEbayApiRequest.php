<?php

namespace App\Actions\Dropshipping\WooCommerce\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

trait WithEbayApiRequest
{
    /**
     * eBay API configuration
     */
    protected function getEbayConfig()
    {
        return [
            'client_id' => $this->ebay_client_id ?? config('services.ebay.client_id'),
            'client_secret' => $this->ebay_client_secret ?? config('services.ebay.client_secret'),
            'redirect_uri' => config('services.ebay.redirect_uri'),
            'sandbox' => config('services.ebay.sandbox', true),
            'access_token' => $this->ebay_access_token ?? null,
            'refresh_token' => $this->ebay_refresh_token ?? null,
        ];
    }

    /**
     * Get eBay API base URL
     */
    protected function getEbayBaseUrl()
    {
        $config = $this->getEbayConfig();
        return $config['sandbox']
            ? 'https://api.sandbox.ebay.com'
            : 'https://api.ebay.com';
    }

    /**
     * Get eBay OAuth base URL
     */
    protected function getEbayOAuthUrl()
    {
        $config = $this->getEbayConfig();
        return $config['sandbox']
            ? 'https://auth.sandbox.ebay.com'
            : 'https://auth.ebay.com';
    }

    /**
     * Generate eBay OAuth authorization URL
     */
    public function getEbayAuthUrl($state = null)
    {
        $config = $this->getEbayConfig();

        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'response_type' => 'code',
            'scope' => 'https://api.ebay.com/oauth/api_scope https://api.ebay.com/oauth/api_scope/sell.marketing.readonly https://api.ebay.com/oauth/api_scope/sell.marketing https://api.ebay.com/oauth/api_scope/sell.inventory.readonly https://api.ebay.com/oauth/api_scope/sell.inventory https://api.ebay.com/oauth/api_scope/sell.account.readonly https://api.ebay.com/oauth/api_scope/sell.account https://api.ebay.com/oauth/api_scope/sell.fulfillment.readonly https://api.ebay.com/oauth/api_scope/sell.fulfillment https://api.ebay.com/oauth/api_scope/sell.analytics.readonly',
        ];

        if ($state) {
            $params['state'] = $state;
        }

        $queryString = http_build_query($params);
        return $this->getEbayOAuthUrl() . "/oauth2/authorize?{$queryString}";
    }

    /**
     * Exchange authorization code for access token
     */
    public function exchangeCodeForToken($authorizationCode)
    {
        $config = $this->getEbayConfig();

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode($config['client_id'] . ':' . $config['client_secret'])
            ])->post($this->getEbayOAuthUrl() . '/identity/v1/oauth2/token', [
                'grant_type' => 'authorization_code',
                'code' => $authorizationCode,
                'redirect_uri' => $config['redirect_uri']
            ]);

            if ($response->successful()) {
                $tokenData = $response->json();

                // Store tokens in the model
                $this->update([
                    'ebay_access_token' => $tokenData['access_token'],
                    'ebay_refresh_token' => $tokenData['refresh_token'],
                    'ebay_token_expires_at' => now()->addSeconds($tokenData['expires_in'])
                ]);

                return $tokenData;
            }

            throw new Exception('Failed to exchange code for token: ' . $response->body());
        } catch (Exception $e) {
            Log::error('eBay OAuth Token Exchange Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshEbayToken()
    {
        $config = $this->getEbayConfig();

        if (!$config['refresh_token']) {
            throw new Exception('No refresh token available');
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode($config['client_id'] . ':' . $config['client_secret'])
            ])->post($this->getEbayOAuthUrl() . '/identity/v1/oauth2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $config['refresh_token']
            ]);

            if ($response->successful()) {
                $tokenData = $response->json();

                // Update stored tokens
                $this->update([
                    'ebay_access_token' => $tokenData['access_token'],
                    'ebay_refresh_token' => $tokenData['refresh_token'] ?? $config['refresh_token'],
                    'ebay_token_expires_at' => now()->addSeconds($tokenData['expires_in'])
                ]);

                return $tokenData;
            }

            throw new Exception('Failed to refresh token: ' . $response->body());
        } catch (Exception $e) {
            Log::error('eBay Token Refresh Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get valid eBay access token (refresh if needed)
     */
    public function getEbayAccessToken()
    {
        $config = $this->getEbayConfig();

        // Check if token exists and is not expired
        if ($config['access_token'] && $this->ebay_token_expires_at && now()->lt($this->ebay_token_expires_at->subMinutes(5))) {
            return $config['access_token'];
        }

        // Try to refresh token if we have a refresh token
        if ($config['refresh_token']) {
            $tokenData = $this->refreshEbayToken();
            return $tokenData['access_token'];
        }

        throw new Exception('No valid access token available. Please re-authenticate with eBay.');
    }

    /**
     * Check if user is authenticated with eBay
     */
    public function isEbayAuthenticated()
    {
        return !empty($this->ebay_access_token) && !empty($this->ebay_refresh_token);
    }

    /**
     * Revoke eBay tokens
     */
    public function revokeEbayTokens()
    {
        try {
            $config = $this->getEbayConfig();

            if ($config['access_token']) {
                Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode($config['client_id'] . ':' . $config['client_secret'])
                ])->post($this->getEbayOAuthUrl() . '/identity/v1/oauth2/revoke', [
                    'token' => $config['access_token']
                ]);
            }

            // Clear stored tokens
            $this->update([
                'ebay_access_token' => null,
                'ebay_refresh_token' => null,
                'ebay_token_expires_at' => null
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('eBay Token Revocation Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Make authenticated request to eBay API
     */
    protected function makeEbayRequest($method, $endpoint, $data = [])
    {
        try {
            $token = $this->getEbayAccessToken();
            $url = $this->getEbayBaseUrl() . $endpoint;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->$method($url, $data);

            if ($response->successful()) {
                return $response->json();
            }

            // If unauthorized, try to refresh token once
            if ($response->status() === 401) {
                $token = $this->refreshEbayToken()['access_token'];
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])->$method($url, $data);

                if ($response->successful()) {
                    return $response->json();
                }
            }

            throw new Exception('eBay API request failed: ' . $response->body());
        } catch (Exception $e) {
            Log::error('eBay API Request Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get user's eBay products/listings
     */
    public function getProducts($limit = 50, $offset = 0)
    {
        try {
            $params = [
                'limit' => $limit,
                'offset' => $offset
            ];

            $queryString = http_build_query($params);
            $endpoint = "/sell/inventory/v1/inventory_item?{$queryString}";

            return $this->makeEbayRequest('get', $endpoint);
        } catch (Exception $e) {
            Log::error('Get eBay Products Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get specific product by SKU
     */
    public function getProduct($sku)
    {
        try {
            $endpoint = "/sell/inventory/v1/inventory_item/{$sku}";
            return $this->makeEbayRequest('get', $endpoint);
        } catch (Exception $e) {
            Log::error('Get eBay Product Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Create/Store product on eBay
     */
    public function storeProduct($productData)
    {
        try {
            $sku = $productData['sku'];
            $endpoint = "/sell/inventory/v1/inventory_item/{$sku}";

            $inventoryItem = [
                'availability' => [
                    'shipToLocationAvailability' => [
                        'quantity' => $productData['quantity'] ?? 1
                    ]
                ],
                'condition' => $productData['condition'] ?? 'NEW',
                'product' => [
                    'title' => $productData['title'],
                    'description' => $productData['description'],
                    'imageUrls' => $productData['images'] ?? [],
                    'aspects' => $productData['aspects'] ?? []
                ]
            ];

            return $this->makeEbayRequest('put', $endpoint, $inventoryItem);
        } catch (Exception $e) {
            Log::error('Store eBay Product Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Update existing product on eBay
     */
    public function updateProduct($sku, $productData)
    {
        try {
            $endpoint = "/sell/inventory/v1/inventory_item/{$sku}";
            return $this->makeEbayRequest('put', $endpoint, $productData);
        } catch (Exception $e) {
            Log::error('Update eBay Product Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Delete product from eBay
     */
    public function deleteProduct($sku)
    {
        try {
            $endpoint = "/sell/inventory/v1/inventory_item/{$sku}";
            return $this->makeEbayRequest('delete', $endpoint);
        } catch (Exception $e) {
            Log::error('Delete eBay Product Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get user's eBay orders
     */
    public function getOrders($limit = 50, $offset = 0, $orderIds = null)
    {
        try {
            $params = [
                'limit' => $limit,
                'offset' => $offset
            ];

            if ($orderIds) {
                $params['orderIds'] = is_array($orderIds) ? implode(',', $orderIds) : $orderIds;
            }

            $queryString = http_build_query($params);
            $endpoint = "/sell/fulfillment/v1/order?{$queryString}";

            return $this->makeEbayRequest('get', $endpoint);
        } catch (Exception $e) {
            Log::error('Get eBay Orders Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get specific order by ID
     */
    public function getOrder($orderId)
    {
        try {
            $endpoint = "/sell/fulfillment/v1/order/{$orderId}";
            return $this->makeEbayRequest('get', $endpoint);
        } catch (Exception $e) {
            Log::error('Get eBay Order Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Fulfill/Ship an order
     */
    public function fulfillOrder($orderId, $fulfillmentData)
    {
        try {
            $endpoint = "/sell/fulfillment/v1/order/{$orderId}/shipping_fulfillment";

            $fulfillment = [
                'lineItems' => $fulfillmentData['line_items'],
                'shippedDate' => $fulfillmentData['shipped_date'] ?? now()->toISOString(),
                'shippingCarrierCode' => $fulfillmentData['carrier_code'] ?? 'USPS',
                'trackingNumber' => $fulfillmentData['tracking_number'] ?? null
            ];

            return $this->makeEbayRequest('post', $endpoint, $fulfillment);
        } catch (Exception $e) {
            Log::error('Fulfill eBay Order Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get buyer/customer information (from orders)
     */
    public function getCustomers($limit = 50, $offset = 0)
    {
        try {
            // eBay doesn't have a direct customer endpoint, we get customers from orders
            $orders = $this->getOrders($limit, $offset);

            if (isset($orders['orders'])) {
                $customers = [];
                foreach ($orders['orders'] as $order) {
                    if (isset($order['buyer'])) {
                        $customers[] = [
                            'username' => $order['buyer']['username'],
                            'email' => $order['buyer']['buyerRegistrationAddress']['email'] ?? null,
                            'name' => $order['buyer']['buyerRegistrationAddress']['fullName'] ?? null,
                            'address' => $order['fulfillmentStartInstructions'][0]['shippingStep']['shipTo'] ?? null
                        ];
                    }
                }
                return array_unique($customers, SORT_REGULAR);
            }

            return $orders;
        } catch (Exception $e) {
            Log::error('Get eBay Customers Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Create eBay listing from inventory item
     */
    public function createListing($sku, $listingData)
    {
        try {
            $endpoint = "/sell/inventory/v1/offer";

            $offer = [
                'sku' => $sku,
                'marketplaceId' => $listingData['marketplace_id'] ?? 'EBAY_US',
                'format' => $listingData['format'] ?? 'FIXED_PRICE',
                'pricingSummary' => [
                    'price' => [
                        'value' => $listingData['price'],
                        'currency' => $listingData['currency'] ?? 'USD'
                    ]
                ],
                'listingDescription' => $listingData['description'] ?? '',
                'categoryId' => $listingData['category_id'],
                'merchantLocationKey' => $listingData['location_key'] ?? 'DEFAULT'
            ];

            return $this->makeEbayRequest('post', $endpoint, $offer);
        } catch (Exception $e) {
            Log::error('Create eBay Listing Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Publish listing to eBay
     */
    public function publishListing($offerId)
    {
        try {
            $endpoint = "/sell/inventory/v1/offer/{$offerId}/publish";
            return $this->makeEbayRequest('post', $endpoint);
        } catch (Exception $e) {
            Log::error('Publish eBay Listing Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get user's eBay account information
     */
    public function getAccountInfo()
    {
        try {
            $endpoint = "/sell/account/v1/account";
            return $this->makeEbayRequest('get', $endpoint);
        } catch (Exception $e) {
            Log::error('Get eBay Account Info Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}
