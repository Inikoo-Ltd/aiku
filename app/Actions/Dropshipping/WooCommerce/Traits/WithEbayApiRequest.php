<?php

namespace App\Actions\Dropshipping\WooCommerce\Traits;

use App\Actions\Dropshipping\Ebay\UpdateEbayUser;
use Illuminate\Support\Arr;
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
            'client_id' => config('services.ebay.client_id'),
            'client_secret' => config('services.ebay.client_secret'),
            'redirect_uri' => config('services.ebay.redirect_uri'),
            'sandbox' => config('services.ebay.sandbox', true),
            'access_token' => Arr::get($this->settings, 'credentials.ebay_access_token'),
            'refresh_token' => Arr::get($this->settings, 'credentials.ebay_refresh_token')
        ];
    }

    /**
     * Get eBay OAuth token endpoint URL
     */
    protected function getEbayTokenUrl()
    {
        $config = $this->getEbayConfig();
        return $config['sandbox']
            ? 'https://api.sandbox.ebay.com/identity/v1/oauth2/token'
            : 'https://api.ebay.com/identity/v1/oauth2/token';
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

        $scopes = [
            'https://api.ebay.com/oauth/api_scope',
            'https://api.ebay.com/oauth/api_scope/sell.marketing.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.marketing',
            'https://api.ebay.com/oauth/api_scope/sell.inventory.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.inventory',
            'https://api.ebay.com/oauth/api_scope/sell.account.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.account',
            'https://api.ebay.com/oauth/api_scope/sell.fulfillment.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.fulfillment',
            'https://api.ebay.com/oauth/api_scope/sell.analytics.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.stores',
            'https://api.ebay.com/oauth/api_scope/sell.stores.readonly',
            'https://api.ebay.com/oauth/api_scope/commerce.identity.readonly'
        ];

        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
        ];

        if ($state) {
            $params['state'] = $state;
        }

        $queryString = http_build_query($params);

        return $this->getEbayOAuthUrl() . "/oauth2/authorize?{$queryString}";
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshEbayToken()
    {
        $config = $this->getEbayConfig();

        $scopes = [
            'https://api.ebay.com/oauth/api_scope',
            'https://api.ebay.com/oauth/api_scope/sell.marketing.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.marketing',
            'https://api.ebay.com/oauth/api_scope/sell.inventory.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.inventory',
            'https://api.ebay.com/oauth/api_scope/sell.account.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.account',
            'https://api.ebay.com/oauth/api_scope/sell.fulfillment.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.fulfillment',
            'https://api.ebay.com/oauth/api_scope/sell.analytics.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.stores',
            'https://api.ebay.com/oauth/api_scope/sell.stores.readonly',
            'https://api.ebay.com/oauth/api_scope/commerce.identity.readonly'
        ];

        if (!$config['refresh_token']) {
            throw new Exception('No refresh token available');
        }

        try {
            $response = Http::asForm()->withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode($config['client_id'] . ':' . $config['client_secret'])
            ])->post($this->getEbayTokenUrl(), [
                'grant_type' => 'refresh_token',
                'refresh_token' => $config['refresh_token'],
                'scope' => implode(' ', $scopes),
            ]);

            if ($response->successful()) {
                $tokenData = $response->json();

                // Update stored tokens
                UpdateEbayUser::run($this, [
                    'settings' => [
                        'credentials' => [
                            'ebay_access_token' => $tokenData['access_token'],
                            'ebay_refresh_token' => $config['refresh_token'],
                            'ebay_token_expires_at' => now()->addSeconds($tokenData['expires_in'])
                        ]
                    ]
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
        if ($config['access_token'] && Arr::get($this->settings, 'credentials.ebay_token_expires_at')) {
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
        return !empty(Arr::get($this->settings, 'credentials.ebay_access_token')) && !empty(Arr::get($this->settings, 'credentials.ebay_refresh_token'));
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
            UpdateEbayUser::run($this, [
                'settings' => [
                    'credentials' => []
                ]
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
            $sku = Arr::get($productData, 'sku');
            $endpoint = "/sell/inventory/v1/inventory_item/{$sku}";

            $inventoryItem = [
                'availability' => [
                    'shipToLocationAvailability' => [
                        'quantity' => Arr::get($productData, 'quantity', 1)
                    ]
                ],
                'condition' => 'NEW',
                'product' => [
                    'title' => Arr::get($productData, 'title'),
                    'description' => Arr::get($productData, 'description'),
                    'imageUrls' => Arr::get($productData, 'images'),
                    'aspects' => Arr::get($productData, 'aspects')
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
     * Update existing product on eBay
     */
    public function updateProductPriceAndQuantity($productData)
    {
        try {
            $endpoint = "/sell/inventory/v1/bulk_update_price_quantity";
            return $this->makeEbayRequest('put', $endpoint, $productData);
        } catch (Exception $e) {
            Log::error('Update eBay Product Price and Quantity Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Store offer on eBay
     */
    public function storeOffer($offerData)
    {
        $data = [
                "sku" => Arr::get($offerData, 'sku'),
                "marketplaceId" => "EBAY_US",
                "format" => "FIXED_PRICE",
                "listingDescription" => Arr::get($offerData, 'description'),
                "availableQuantity" => Arr::get($offerData, 'quantity', 1),
                "quantityLimitPerBuyer" => 10,
                "pricingSummary" => [
                    "price" => [
                        "value" => Arr::get($offerData, 'price', 0),
                        "currency" => Arr::get($offerData, 'currency', 'USD')
                    ]
                ],
                "listingPolicies" => [
                    "fulfillmentPolicyId" => Arr::get($offerData, 'fulfillment_policy_id'),
                    "paymentPolicyId" => Arr::get($offerData, 'payment_policy_id'),
                    "returnPolicyId" => Arr::get($offerData, 'return_policy_id')
                ],
                "categoryId" => Arr::get($offerData, 'category_id'),
                "merchantLocationKey" => Arr::get($offerData, 'location_key'),
                // "tax" => [
                //     "vatPercentage" => 10.2,
                //     "applyTax" => true,
                //     "thirdPartyTaxCategory" => "Electronics"
                // ] //TODO: Add tax if needed
        ];
        try {
            $endpoint = "/sell/inventory/v1/offer";
            return $this->makeEbayRequest('post', $endpoint, $data);
        } catch (Exception $e) {
            Log::error('Create eBay Product Offer: ' . $e->getMessage());
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
            $endpoint = "/sell/stores/v1/store";
            return $this->makeEbayRequest('get', $endpoint);
        } catch (Exception $e) {
            Log::error('Get eBay Account Info Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Create user's eBay account opt in
     */
    public function createOptInProgram()
    {
        $optInProgram = [
            'programType' => 'SELLING_POLICY_MANAGEMENT'
        ];

        try {
            $endpoint = "/sell/account/v1/program/opt_in";
            return $this->makeEbayRequest('post', $endpoint, $optInProgram);
        } catch (Exception $e) {
            Log::error('Opt in Program Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get user's eBay account opt in
     */
    public function getOptInProgram()
    {
        try {
            $endpoint = "/sell/account/v1/program/get_opted_in_programs";
            return $this->makeEbayRequest('get', $endpoint);
        } catch (Exception $e) {
            Log::error('Get Opt in Program Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Create user's eBay fulfilment policy
     */
    public function createFulfilmentPolicy()
    {
        $data = [
            "categoryTypes" => [
                [
                "name" => "ALL_EXCLUDING_MOTORS_VEHICLES"
                ]
            ],
            "marketplaceId" => "EBAY_US",
            "name" => "Domestic free shipping",
            "handlingTime" => [
                "unit"  => "DAY",
                "value"  => "1"
            ],
            "shippingOptions" => [
                [
                "costType" => "FLAT_RATE",
                "optionType" => "DOMESTIC",
                "shippingServices" => [
                        [
                        "buyerResponsibleForShipping" => "false",
                        "freeShipping" => "true",
                        "shippingCarrierCode" => "USPS",
                        "shippingServiceCode" => "USPSPriorityFlatRateBox"
                        ]
                    ]
                ]
            ]
        ];

        try {
            $endpoint = "/sell/account/v1/fulfillment_policy";
            return $this->makeEbayRequest('post', $endpoint, $data);
        } catch (Exception $e) {
            Log::error('Create Fulfilment Policy Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Create user's eBay payment policy
     */
    public function createPaymentPolicy()
    {
        $data = [
            "name" => "minimal Payment Policy",
            "marketplaceId" => "EBAY_US",
            "categoryTypes" => [
                [
                "name" => "ALL_EXCLUDING_MOTORS_VEHICLES"
                ]
            ],
            "paymentMethods" => [
            ]
        ];

        try {
            $endpoint = "/sell/account/v1/payment_policy";
            return $this->makeEbayRequest('post', $endpoint, $data);
        } catch (Exception $e) {
            Log::error('Create Payment Policy Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Create user's eBay return policy
     */
    public function createReturnPolicy()
    {
        $data = [
            "name" => "minimal return policy, US marketplace",
            "marketplaceId" => "EBAY_US",
            "refundMethod" => "MONEY_BACK",
            "returnsAccepted" => true,
            "returnShippingCostPayer" => "SELLER",
            "returnPeriod" => [
                "value" => 30,
                "unit" => "DAY"
            ]
        ];

        try {
            $endpoint = "/sell/account/v1/return_policy";
            return $this->makeEbayRequest('post', $endpoint, $data);
        } catch (Exception $e) {
            Log::error('Create Return Policy Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

        /**
     * Get user's eBay inventory location
     */
    public function getInventoryLocations()
    {
        try {
            $endpoint = "/sell/inventory/v1/location?limit=20&offset=0";
            return $this->makeEbayRequest('get', $endpoint);
        } catch (Exception $e) {
            Log::error('Get Inventory Location Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

        /**
     * Create user's eBay inventory location
     */
    public function createInventoryLocation($locationData)
    {
        $data = [
                "location" => [
                "address" => [
                    "city" => Arr::get($locationData, 'city'),
                    "stateOrProvince" => Arr::get($locationData, 'state'),
                    "country" => Arr::get($locationData, 'country'),
                ]
            ],
            "name" => Arr::get($locationData, 'name', 'Default Location'),
            "merchantLocationStatus" => "ENABLED",
            "locationTypes" => [
                "WAREHOUSE"
            ]
        ];

        try {
            $locationKey = Arr::get($locationData, 'locationKey');
            $endpoint = "/sell/inventory/v1/location/{$locationKey}";
            return $this->makeEbayRequest('post', $endpoint, $data);
        } catch (Exception $e) {
            Log::error('Create Inventory Location Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}
