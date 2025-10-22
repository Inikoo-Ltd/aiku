<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 11 Jun 2025 16:19:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Traits;

use App\Actions\Dropshipping\Ebay\UpdateEbayUser;
use App\Exceptions\Dropshipping\Ebay\EbayApiException;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait WithEbayApiRequest
{
    /**
     * Fields that require user action to fix
     */
    public const array ACTIONABLE_FIELDS = [
        'sku',
        'title',
        'description',
        'price',
        'quantity',
        'availableQuantity',
        'categoryId',
        'imageUrls',
        'aspects',
        'brand',
        'mpn',
        'upc',
        'ean',
        'isbn',
        'condition',
        'conditionDescription',
        'format',
        'listingPolicies',
        'fulfillmentPolicyId',
        'paymentPolicyId',
        'returnPolicyId',
        'merchantLocationKey',
        'pricingSummary',
    ];

    public function sanitizeForEbay($text): string
    {
        // Remove or replace problematic keywords
        $problematic = [
            'includes' => 'contains',
            'include' => 'contain',
            'javascript' => '',
            '.cookie' => '',
            'cookie(' => '',
            'replace(' => '',
            'IFRAME' => '',
            'META' => '',
            'base href' => '',
        ];

        $text = str_ireplace(array_keys($problematic), array_values($problematic), $text);

        return preg_replace('/\b(includes?|javascript)\b/i', '', $text);
    }

    /**
     * Check if error requires user action
     */
    public static function isActionableError($errorResponse): bool
    {
        if (empty($errorResponse)) {
            return false;
        }

        $errors = is_string($errorResponse) ? json_decode($errorResponse, true) : $errorResponse;

        if (!isset($errors['errors']) || !is_array($errors['errors'])) {
            return false;
        }

        foreach ($errors['errors'] as $error) {
            // Check if error has parameters with field names
            if (isset($error['parameters']) && is_array($error['parameters'])) {
                foreach ($error['parameters'] as $param) {
                    if (isset($param['name']) && in_array($param['name'], self::ACTIONABLE_FIELDS)) {
                        return true;
                    }
                }
            }

            // Check inputRefIds for field references (e.g., "$.product.title")
            if (isset($error['inputRefIds']) && is_array($error['inputRefIds'])) {
                foreach ($error['inputRefIds'] as $inputRef) {
                    foreach (self::ACTIONABLE_FIELDS as $field) {
                        if (str_contains(strtolower($inputRef), strtolower($field))) {
                            return true;
                        }
                    }
                }
            }

            // Check error category - REQUEST errors often need user action
            if (isset($error['category']) && $error['category'] === 'REQUEST') {
                // Check if message mentions any actionable fields
                $message = strtolower($error['message'] ?? '');
                foreach (self::ACTIONABLE_FIELDS as $field) {
                    if (str_contains($message, strtolower($field))) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get user-friendly error messages for display
     */
    public static function getDisplayErrors($errorResponse): ?array
    {
        if (!self::isActionableError($errorResponse)) {
            return null;
        }

        $errors = is_string($errorResponse) ? json_decode($errorResponse, true) : $errorResponse;
        $displayErrors = [];

        foreach ($errors['errors'] as $error) {
            $fieldName = null;
            $errorMessage = $error['message'] ?? 'Unknown error';

            // Extract field name from parameters
            if (isset($error['parameters'])) {
                foreach ($error['parameters'] as $param) {
                    if (isset($param['name']) && in_array($param['name'], self::ACTIONABLE_FIELDS)) {
                        $fieldName = $param['name'];
                        break;
                    }
                }
            }

            // Extract field name from inputRefIds
            if (!$fieldName && isset($error['inputRefIds'])) {
                foreach ($error['inputRefIds'] as $inputRef) {
                    // Parse field name from JSON path like "$.product.title"
                    if (preg_match('/\$\.(?:\w+\.)*(\w+)/', $inputRef, $matches)) {
                        $possibleField = $matches[1];
                        if (in_array($possibleField, self::ACTIONABLE_FIELDS)) {
                            $fieldName = $possibleField;
                            break;
                        }
                    }
                }
            }

            // Try to extract field from error message
            if (!$fieldName) {
                foreach (self::ACTIONABLE_FIELDS as $field) {
                    if (stripos($errorMessage, $field) !== false) {
                        $fieldName = $field;
                        break;
                    }
                }
            }

            if ($fieldName) {
                if (!isset($displayErrors[$fieldName])) {
                    $displayErrors[$fieldName] = [];
                }
                $displayErrors[$fieldName][] = $errorMessage;
            }
        }

        return !empty($displayErrors) ? $displayErrors : null;
    }

    /**
     * eBay API configuration
     */
    protected function getEbayConfig(): array
    {

        return [
            'client_id' => config('services.ebay.client_id'),
            'client_secret' => config('services.ebay.client_secret'),
            'redirect_uri' => config('services.ebay.redirect_uri'),
            'sandbox' => config('services.ebay.sandbox'),
            'access_token' => Arr::get($this->settings, 'credentials.ebay_access_token'),
            'refresh_token' => Arr::get($this->settings, 'credentials.ebay_refresh_token')
        ];
    }

    /**
     * Get eBay OAuth token endpoint URL
     */
    protected function getEbayTokenUrl(): string
    {
        $config = $this->getEbayConfig();
        return $config['sandbox']
            ? 'https://api.sandbox.ebay.com/identity/v1/oauth2/token'
            : 'https://api.ebay.com/identity/v1/oauth2/token';
    }

    /**
     * Get eBay API base URL
     */
    protected function getEbayBaseUrl(): string
    {
        $config = $this->getEbayConfig();
        return $config['sandbox']
            ? 'https://api.sandbox.ebay.com'
            : 'https://api.ebay.com';
    }

    /**
     * Get eBay OAuth base URL
     */
    protected function getEbayOAuthUrl(): string
    {
        $config = $this->getEbayConfig();
        return $config['sandbox']
            ? 'https://auth.sandbox.ebay.com'
            : 'https://auth.ebay.com';
    }

    /**
     * Generate eBay OAuth authorization URL
     */
    public function getEbayAuthUrl($state = null): string
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


        return $this->getEbayOAuthUrl() . "/oauth2/authorize?$queryString";
    }

    /**
     * Refresh access token using refresh token
     *
     * @throws \Exception
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
            throw new EbayApiException('No refresh token available');
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

        } catch (Exception $e) {
            Log::error('eBay Token Refresh Error: ' . $e->getMessage());
        }
    }

    /**
     * Get a valid eBay access token (refresh if needed)
     *
     * @throws \Exception
     */
    public function getEbayAccessToken()
    {
        $config = $this->getEbayConfig();

        // Check if a token exists and is not expired
        if ($config['access_token'] && Arr::get($this->settings, 'credentials.ebay_token_expires_at')) {
            return $config['access_token'];
        }

        // Try to refresh the token if we have a refresh token
        if ($config['refresh_token']) {
            $tokenData = $this->refreshEbayToken();
            return $tokenData['access_token'];
        }
    }

    /**
     * Check if a user is authenticated with eBay
     */
    public function isEbayAuthenticated(): bool
    {
        return !empty(Arr::get($this->settings, 'credentials.ebay_access_token')) && !empty(Arr::get($this->settings, 'credentials.ebay_refresh_token'));
    }

    /**
     * Revoke eBay tokens
     */
    public function revokeEbayTokens(): bool
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
     * Make an authenticated request to eBay API
     *
     * @throws \Exception
     */
    protected function makeEbayRequest($method, $endpoint, $data = [], $queryParams = [])
    {
        try {
            $token = $this->getEbayAccessToken();
            $url = $this->getEbayBaseUrl() . $endpoint;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Content-Language' => 'en-GB'
            ])->withQueryParameters($queryParams)
                ->$method($url, $data);

            if ($response->successful()) {
                return $response->json();
            }

            // If unauthorized, try to refresh the token once
            if ($response->status() === 401) {
                $token = $this->refreshEbayToken()['access_token'];
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Content-Language' => 'en-GB'
                ])->$method($url, $data);

                if ($response->successful()) {
                    return $response->json();
                }
            }

        } catch (Exception $e) {
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
            $endpoint = "/sell/inventory/v1/inventory_item?$queryString";

            return $this->makeEbayRequest('get', $endpoint);
        } catch (Exception $e) {
            Log::error('Get eBay Products Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Search products by SKU or title
     */
    public function searchProducts($query, $type = 'sku', $limit = 50, $offset = 0)
    {
        try {
            $params = [
                'limit' => $limit,
                'offset' => $offset
            ];

            if ($type === 'title') {
                $params['q'] = $query;
            } elseif ($type === 'id') {
                $params['id'] = $query;
            } else {
                $params['sku'] = $query;
            }

            $queryString = http_build_query($params);
            $endpoint = "/sell/inventory/v1/inventory_item?$queryString";

            return $this->makeEbayRequest('get', $endpoint);
        } catch (Exception $e) {
            Log::error('Search eBay Products Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get a specific product by SKU
     */
    public function getProduct($sku)
    {
        try {
            $endpoint = "/sell/inventory/v1/inventory_item/$sku";
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
            $sku = Arr::pull($productData, 'sku');
            $endpoint = "/sell/inventory/v1/inventory_item/$sku";

            return $this->makeEbayRequest('put', $endpoint, $productData);
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
            $endpoint = "/sell/inventory/v1/inventory_item/$sku";
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
            "marketplaceId" => "EBAY_GB",
            "format" => "FIXED_PRICE",
            "listingDescription" => Arr::get($offerData, 'description'),
            "availableQuantity" => Arr::get($offerData, 'quantity', 1),
            "pricingSummary" => [
                "price" => [
                    "value" => Arr::get($offerData, 'price', 0),
                    "currency" => Arr::get($offerData, 'currency', 'GBP')
                ]
            ],
            "listingPolicies" => [
                "fulfillmentPolicyId" => Arr::get($this->settings, 'defaults.main_fulfilment_policy_id'),
                "paymentPolicyId" => Arr::get($this->settings, 'defaults.main_payment_policy_id'),
                "returnPolicyId" => Arr::get($this->settings, 'defaults.main_return_policy_id'),
            ],
            "categoryId" => Arr::get($offerData, 'category_id'),
            "merchantLocationKey" => Arr::get($this->settings, 'defaults.main_location_key'),

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
     * Get listing by SKU
     */
    public function getListing($sku)
    {
        try {
            $endpoint = "/sell/inventory/v1/listing/$sku";
            return $this->makeEbayRequest('get', $endpoint);
        } catch (Exception $e) {
            Log::error('Get eBay Listing Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function getListings($limit = 50, $offset = 0)
    {
        try {
            $endpoint = "/sell/inventory/v1/listing";
            return $this->makeEbayRequest('get', $endpoint, [], [
                'limit' => $limit,
                'offset' => $offset
            ]);
        } catch (Exception $e) {
            Log::error('Get eBay Listings Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get offers by inventory item SKU
     */
    public function getOffers($fields)
    {
        try {
            $endpoint = "/sell/inventory/v1/offer";
            return $this->makeEbayRequest('get', $endpoint, [], $fields);
        } catch (Exception $e) {
            Log::error('Get eBay Offers Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get offer by offer ID
     */
    public function getOffer($offerId)
    {
        try {
            $endpoint = "/sell/inventory/v1/offer/$offerId";
            return $this->makeEbayRequest('get', $endpoint);
        } catch (Exception $e) {
            Log::error('Get eBay Offer Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }



    /**
     * Update offer by offer ID
     */
    public function updateOffer($offerId, array $offerData)
    {
        try {
            $data = [
                "sku" => Arr::get($offerData, 'sku'),
                "marketplaceId" => "EBAY_GB",
                "format" => "FIXED_PRICE",
                "listingDescription" => Arr::get($offerData, 'description'),
                "availableQuantity" => Arr::get($offerData, 'quantity', 1),
                "pricingSummary" => [
                    "price" => [
                        "value" => Arr::get($offerData, 'price', 0),
                        "currency" => Arr::get($offerData, 'currency', 'GBP')
                    ]
                ],
                "listingPolicies" => [
                    "fulfillmentPolicyId" => Arr::get($this->settings, 'defaults.main_fulfilment_policy_id'),
                    "paymentPolicyId" => Arr::get($this->settings, 'defaults.main_payment_policy_id'),
                    "returnPolicyId" => Arr::get($this->settings, 'defaults.main_return_policy_id'),
                ],
                "categoryId" => Arr::get($offerData, 'category_id'),
                "merchantLocationKey" => Arr::get($this->settings, 'defaults.main_location_key'),

            ];

            $endpoint = "/sell/inventory/v1/offer/$offerId";
            return $this->makeEbayRequest('put', $endpoint, $data);
        } catch (Exception $e) {
            Log::error('Get eBay Offer Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Delete product from eBay
     */
    public function deleteProduct($sku)
    {
        try {
            $endpoint = "/sell/inventory/v1/inventory_item/$sku";
            return $this->makeEbayRequest('delete', $endpoint);
        } catch (Exception $e) {
            Log::error('Delete eBay Product Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Delete offer from eBay
     */
    public function withdrawOffer($offerId)
    {
        try {
            $endpoint = "/sell/inventory/v1/offer/$offerId/withdraw";
            return $this->makeEbayRequest('post', $endpoint);
        } catch (Exception $e) {
            Log::error('Delete eBay Offer Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get user's eBay orders
     */
    public function getOrders($limit = 50, $offset = 0, $orderIds = null, $filter = null)
    {
        try {
            $params = [
                'limit' => $limit,
                'offset' => $offset
            ];

            if ($orderIds) {
                $params['orderIds'] = is_array($orderIds) ? implode(',', $orderIds) : $orderIds;
            }

            if ($filter) {
                $params['filter'] = $filter;

            }


            $queryString = http_build_query($params);
            $endpoint = "/sell/fulfillment/v1/order?$queryString";

            return $this->makeEbayRequest('get', $endpoint);
        } catch (Exception $e) {
            Log::error('Get eBay Orders Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get a specific order by ID
     */
    public function getOrder($orderId)
    {
        try {
            $endpoint = "/sell/fulfillment/v1/order/$orderId";
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
            $endpoint = "/sell/fulfillment/v1/order/$orderId/shipping_fulfillment";

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
            $endpoint = "/sell/inventory/v1/offer/$offerId/publish";
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
     * Create a user's eBay account opt in
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
     * Get a user's eBay account to opt in
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
     * Create a user's eBay fulfilment policy
     */
    public function createFulfilmentPolicy()
    {
        $data = [
            "categoryTypes" => [
                [
                    "name" => "ALL_EXCLUDING_MOTORS_VEHICLES"
                ]
            ],
            "marketplaceId" => "EBAY_GB",
            "name" => "Domestic shipping",
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
                            "shippingCarrierCode" => "RoyalMail",
                            "shippingServiceCode" => "UK_RoyalMailNextDay"
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
     * Get user's eBay Fulfillment Policies
     */
    public function getFulfilmentPolicies()
    {
        try {
            $endpoint = "/sell/account/v1/fulfillment_policy";
            return $this->makeEbayRequest('get', $endpoint, [], [
                'marketplace_id' => 'EBAY_GB'
            ]);
        } catch (Exception $e) {
            Log::error('Get Fulfilment Policy Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Create a user's eBay payment policy
     */
    public function createPaymentPolicy()
    {
        $data = [
            "name" => "minimal Payment Policy",
            "marketplaceId" => "EBAY_GB",
            "categoryTypes" => [
                [
                    "name" => "ALL_EXCLUDING_MOTORS_VEHICLES"
                ]
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
     * Get user's eBay Payment Policies
     */
    public function getPaymentPolicies()
    {
        try {
            $endpoint = "/sell/account/v1/payment_policy";
            return $this->makeEbayRequest('get', $endpoint, [], [
                'marketplace_id' => 'EBAY_GB'
            ]);
        } catch (Exception $e) {
            Log::error('Get Payment Policy Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Create a user's eBay return policy
     */
    public function createReturnPolicy()
    {
        $data = [
            "name" => "minimal return policy",
            "marketplaceId" => "EBAY_GB",
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
     * Get user's eBay Return Policies
     */
    public function getReturnPolicies()
    {
        try {
            $endpoint = "/sell/account/v1/return_policy";
            return $this->makeEbayRequest('get', $endpoint, [], [
                'marketplace_id' => 'EBAY_GB'
            ]);
        } catch (Exception $e) {
            Log::error('Get Return Policy Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get a user's eBay inventory location
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
     * Create a user's eBay inventory location
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
            $endpoint = "/sell/inventory/v1/location/$locationKey";
            return $this->makeEbayRequest('post', $endpoint, $data);
        } catch (Exception $e) {
            Log::error('Create Inventory Location Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get user's eBay category suggestions
     */
    public function getCategorySuggestions($keyword)
    {
        try {
            $encodedKeyword = urlencode($keyword);
            $endpoint = "/commerce/taxonomy/v1/category_tree/3/get_category_suggestions";
            return $this->makeEbayRequest('get', $endpoint, [], [
                'q' => $encodedKeyword
            ]);
        } catch (Exception $e) {
            Log::error('Get Category Suggestions Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get user's eBay category suggestions
     */
    public function searchAvailableProducts($keyword)
    {
        try {
            $endpoint = "/buy/browse/v1/item_summary/search";
            return $this->makeEbayRequest('get', $endpoint, [], [
                'q' => $keyword,
                'limit' => 10
            ]);
        } catch (Exception $e) {
            Log::error('Get Category Suggestions Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get user's eBay category suggestions
     *
     */
    public function getUser($data = [], $queryParams = [])
    {
        try {
            $token = $this->getEbayAccessToken();
            $url = "https://apiz.ebay.com/commerce/identity/v1/user/";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Content-Language' => 'en-GB'
            ])->withQueryParameters($queryParams)
                ->get($url, $data);

            if ($response->successful()) {
                return $response->json();
            }

            // If unauthorized, try to refresh the token once
            if ($response->status() === 401) {
                $token = $this->refreshEbayToken()['access_token'];
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Content-Language' => 'en-GB'
                ])->get($url, $data);

                if ($response->successful()) {
                    return $response->json();
                }
            }

        } catch (Exception $e) {
            Log::error('eBay API Request Error: ' . $e->getMessage());
        }
    }
}
