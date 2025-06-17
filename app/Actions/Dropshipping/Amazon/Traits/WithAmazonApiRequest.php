<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 11 Jun 2025 16:19:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Amazon\Traits;

use App\Actions\Dropshipping\Amazon\UpdateAmazonUser;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait WithAmazonApiRequest
{
    /**
     * Amazon SP-API configuration
     */
    protected function getAmazonConfig()
    {
        $config = [
            'app_id' => config('services.amazon.app_id'),
            'client_id' => config('services.amazon.client_id'),
            'client_secret' => config('services.amazon.client_secret'),
            'redirect_uri' => config('services.amazon.redirect_uri'),
            'region' => config('services.amazon.region', 'eu'),
            'sandbox' => config('services.amazon.sandbox'),
            'access_token' => Arr::get($this->settings, 'credentials.amazon_access_token'),
            'refresh_token' => Arr::get($this->settings, 'credentials.amazon_refresh_token'),
            'marketplace_id' => Arr::get($this->settings, 'credentials.marketplace_id'),
        ];

        // Set token sources based on environment
        /*if ($sandbox) {
            $storedAccessToken = config('services.amazon.access_token', null);
            if ($storedAccessToken) {
                $config['access_token'] = $storedAccessToken;
            } else {
                // If no stored token, generate a new one
                $config['access_token'] = $this->getLWAAccessToken([
                    'client_id' => config('services.amazon.client_id'),
                    'client_secret' => config('services.amazon.client_secret'),
                    'refresh_token' => config('services.amazon.refresh_token')
                ]);
                config(['services.amazon.access_token' =>
                    $config['access_token']
                ]);
            }
            $marketplaceId = config('services.amazon.marketplace_id', null);
            if ($marketplaceId) {
                $config['marketplace_id'] = $marketplaceId;
            } else {
                config(['services.amazon.marketplace_id' => $this->getAmazonMarketplaceId()]);
            }
            $config['refresh_token'] = config('services.amazon.refresh_token');
        } else {
            $config['access_token'] = Arr::get($this->settings, 'credentials.amazon_access_token');
            $config['refresh_token'] = Arr::get($this->settings, 'credentials.amazon_refresh_token');
        }*/

        return $config;
    }

    /**
     * Get Amazon OAuth token endpoint URL
     */
    protected function getAmazonTokenUrl()
    {
        // $config = $this->getAmazonConfig();
        // return $config['sandbox']
        //     ? 'https://api.sandbox.amazon.com/auth/o2/token'
        //     : 'https://api.amazon.com/auth/o2/token';
        return 'https://api.amazon.com/auth/o2/token';
    }

    /**
     * Get Amazon SP-API base URL
     */
    protected function getAmazonBaseUrl()
    {
        $config = $this->getAmazonConfig();
        $region = $config['region'];

        return $config['sandbox']
            ? "https://sandbox.sellingpartnerapi-{$region}.amazon.com"
            : "https://sellingpartnerapi-{$region}.amazon.com";
    }

    /**
     * Get Amazon OAuth base URL
     */
    protected function getAmazonOAuthUrl()
    {
        return 'https://sellercentral.amazon.com/apps/authorize/consent';
    }

    /**
     * Generate Amazon OAuth authorization URL
     */
    public function getAmazonAuthUrl($state = null)
    {
        $config = $this->getAmazonConfig();

        $params = [
            'application_id' => $config['app_id'],
            'amazon_callback_uri' => $config['redirect_uri'],
            'state' => $state ?? md5(time()),
        ];

        $queryString = http_build_query($params);

        return match ($config['sandbox']) {
            true => "https://sellercentral.amazon.com/apps/authorize/consent?version=beta&{$queryString}",
            default => "https://sellercentral.amazon.com/apps/authorize/consent?{$queryString}"
        };
    }

    public function getAmazonMarketplaceId(): string
    {
        $token = $this->refreshAmazonToken()['access_token'];

        $res = Http::withHeaders([
            'x-amz-access-token' => $token,
        ])->get("{$this->getAmazonBaseUrl()}/sellers/v1/marketplaceParticipations");

        if ($res->successful()) {
            $data = $res->json();

            if (isset($data['payload']) && is_array($data['payload']) && count($data['payload']) > 0) {
                return Arr::get($data, 'payload.0.marketplace.id');
            }
        }
        return $res;
    }

    /**
     * Exchange authorization code for tokens
     */
    public function getAmazonTokens($authorizationCode)
    {
        $config = $this->getAmazonConfig();

        try {
            $response = Http::asForm()->post($this->getAmazonTokenUrl(), [
                'grant_type' => 'authorization_code',
                'code' => $authorizationCode,
                'redirect_uri' => $config['redirect_uri'],
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret']
            ]);

            if ($response->successful()) {
                $tokenData = $response->json();

                // Update stored tokens
                // UpdateAmazonUser::run($this, [
                //     'settings' => [
                //         'credentials' => [
                //             'amazon_access_token' => $tokenData['access_token'],
                //             'amazon_refresh_token' => $tokenData['refresh_token'],
                //             'amazon_token_expires_at' => now()->addSeconds($tokenData['expires_in'])
                //         ]
                //     ]
                // ]);

                return $tokenData;
            }

            throw new Exception('Failed to obtain Amazon tokens: ' . $response->body());
        } catch (Exception $e) {
            Log::error('Amazon Token Exchange Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshAmazonToken()
    {
        $config = $this->getAmazonConfig();

        if (!$config['refresh_token']) {
            throw new Exception('No refresh token available');
        }

        try {
            $response = Http::asForm()->post($this->getAmazonTokenUrl(), [
                'grant_type' => 'refresh_token',
                'refresh_token' => $config['refresh_token'],
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret']
            ]);

            if ($response->successful()) {
                $tokenData = $response->json();

                // Update stored tokens
                // UpdateAmazonUser::run($this, [
                //     'settings' => [
                //         'credentials' => [
                //             'amazon_access_token' => $tokenData['access_token'],
                //             'amazon_token_expires_at' => now()->addSeconds($tokenData['expires_in']),
                //             'amazon_refresh_token' => $config['refresh_token']
                //         ]
                //     ]
                // ]);

                return $tokenData;
            }

            throw new Exception('Failed to refresh token: ' . $response->body());
        } catch (Exception $e) {
            Log::error('Amazon Token Refresh Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get valid Amazon access token (refresh if needed)
     */
    public function getAmazonAccessToken()
    {
        $config = $this->getAmazonConfig();
        $expiresAt = Arr::get($this->settings, 'credentials.amazon_token_expires_at');

        // Check if token exists and is not expired
        if ($config['access_token'] && $expiresAt && now()->lt($expiresAt)) {
            return $config['access_token'];
        }

        // Try to refresh token if we have a refresh token
        if ($config['refresh_token']) {
            $tokenData = $this->refreshAmazonToken();

            UpdateAmazonUser::run($this, [
                'settings' => [
                    'credentials' => [
                        ...Arr::get($this->settings, 'credentials', []),
                        'amazon_access_token' => $tokenData['access_token'],
                        'amazon_token_expires_at' => now()->addSeconds($tokenData['expires_in'])
                    ]
                ]
            ]);

            return $tokenData['access_token'];
        }

        throw new Exception('No valid access token available. Please re-authenticate with Amazon.');
    }

    /**
     * Check if user is authenticated with Amazon
     */
    public function isAmazonAuthenticated()
    {
        return !empty(Arr::get($this->settings, 'credentials.amazon_access_token')) &&
            !empty(Arr::get($this->settings, 'credentials.amazon_refresh_token'));
    }

    /**
     * Revoke Amazon tokens
     */
    public function revokeAmazonTokens()
    {
        try {
            // Clear stored tokens
            // UpdateAmazonUser::run($this, [
            //     'settings' => [
            //         'credentials' => []
            //     ]
            // ]);

            return true;
        } catch (Exception $e) {
            Log::error('Amazon Token Revocation Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate LWA (Login with Amazon) access token for restricted operations
     */
    protected function getLWAAccessToken($config = null)
    {
        if (!$config) {
            $config = $this->getAmazonConfig();
        }

        try {
            $response = Http::asForm()->post($this->getAmazonTokenUrl(), [
                'grant_type' => 'refresh_token',
                'refresh_token' => $config['refresh_token'],
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret']
            ]);

            if ($response->successful()) {
                return $response->json()['access_token'];
            }

            throw new Exception('Failed to obtain LWA token: ' . $response->body());
        } catch (Exception $e) {
            Log::error('Amazon LWA Token Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate signature for Amazon SP-API requests
     */
    protected function generateAmazonSignature($method, $endpoint, $queryParams = [])
    {
        // In a real implementation, this would generate the required AWS Signature V4
        // This is a placeholder that would need to be implemented with a proper AWS signature library
        // You might want to use a package like aws/aws-sdk-php for this

        // Return a dummy signature for now - this needs to be properly implemented
        return ['Authorization' => 'Bearer ' . $this->getAmazonAccessToken()];
    }

    /**
     * Make authenticated request to Amazon SP-API
     */
    protected function makeAmazonRequest($method, $endpoint, $data = [], $queryParams = [])
    {
        try {
            $url = $this->getAmazonBaseUrl() . $endpoint;
            $config = $this->getAmazonConfig();
            $token = $config['access_token'];

            // Add marketplace_id to query parameters if not present
            if (!isset($queryParams['MarketplaceIds']) && !isset($queryParams['MarketplaceId'])) {
                $queryParams['MarketplaceIds'] = $config['marketplace_id'];
            }

            if ($config['sandbox']) {
                $queryParams['CreatedAfter'] = 'TEST_CASE_200';
            }

            // Note: In production, you would need to implement the proper AWS Signature V4 here
            $headers = [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'x-amz-access-token' => $token
            ];

            $response = Http::withHeaders($headers)->withQueryParameters($queryParams)->$method($url, $data);

            if ($response->successful()) {
                return $response->json();
            }

            // If unauthorized, try to refresh token once
            if ($response->status() === 401 || $response->status() === 403) {
                $token = $this->refreshAmazonToken()['access_token'];
                $headers['Authorization'] = 'Bearer ' . $token;
                $headers['x-amz-access-token'] = $token;

                $response = Http::withHeaders($headers)->$method($url, $data);

                if ($response->successful()) {
                    return $response->json();
                }
            }

            throw new Exception('Amazon API request failed: ' . $response->body());
        } catch (Exception $e) {
            Log::error('Amazon API Request Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get user's Amazon catalog items
     */
    public function getProducts($keywords, $limit = 50, $nextToken = null)
    {
        try {
            $queryParams = [
                'pageSize' => $limit,
                'keywords' => $keywords
            ];

            if ($nextToken) {
                $queryParams['nextToken'] = $nextToken;
            }

            $endpoint = "/catalog/2022-04-01/items";

            return $this->makeAmazonRequest('get', $endpoint, [], $queryParams);
        } catch (Exception $e) {
            Log::error('Get Amazon Products Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get specific product by ASIN
     */
    public function getProductByAsin($asin)
    {
        try {
            $endpoint = "/catalog/2022-04-01/items/{$asin}";
            return $this->makeAmazonRequest('get', $endpoint);
        } catch (Exception $e) {
            Log::error('Get Amazon Product Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get seller's listings
     */
    public function getSellerListings($nextToken = null)
    {
        try {
            $queryParams = [];

            if ($nextToken) {
                $queryParams['nextToken'] = $nextToken;
            }

            $endpoint = "/listings/2021-08-01/items";

            return $this->makeAmazonRequest('get', $endpoint, [], $queryParams);
        } catch (Exception $e) {
            Log::error('Get Amazon Seller Listings Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get product details by SKU
     */
    public function getProductBySku($sku)
    {
        try {
            $config = $this->getAmazonConfig();
            $endpoint = "/listings/2021-08-01/items/{$this->id}/{$sku}";
            $queryParams = [
                'marketplaceIds' => [$config['marketplace_id']],
                'includedData' => 'summaries'
            ];

            return $this->makeAmazonRequest('get', $endpoint, [], $queryParams);
        } catch (Exception $e) {
            Log::error('Get Amazon Product by SKU Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Create/Update product listing on Amazon
     */
    public function upsertProduct($sku, $productData)
    {
        try {
            $sellerId = $this->id;
            $endpoint = "/listings/2021-08-01/items/{$sellerId}/{$sku}";

            return $this->makeAmazonRequest('put', $endpoint, $productData);
        } catch (Exception $e) {
            Log::error('Upsert Amazon Product Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Delete product from Amazon
     */
    public function deleteProduct($sku)
    {
        try {
            $config = $this->getAmazonConfig();
            $endpoint = "/listings/2021-08-01/items/{$sku}";
            $queryParams = [
                'marketplaceIds' => [$config['marketplace_id']]
            ];

            return $this->makeAmazonRequest('delete', $endpoint, [], $queryParams);
        } catch (Exception $e) {
            Log::error('Delete Amazon Product Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Update product price
     */
    public function updateProductPrice($sku, $price, $currency = 'USD')
    {
        try {
            $productData = [
                'attributes' => [
                    'price' => [
                        'value' => $price,
                        'currency' => $currency
                    ]
                ]
            ];

            return $this->upsertProduct($sku, $productData);
        } catch (Exception $e) {
            Log::error('Update Amazon Product Price Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Update product inventory
     */
    public function updateProductInventory($sku, $quantity)
    {
        try {
            $productData = [
                'attributes' => [
                    'fulfillment_availability' => [
                        [
                            'quantity' => $quantity,
                            'fulfillment_channel_code' => 'DEFAULT'
                        ]
                    ]
                ]
            ];

            return $this->upsertProduct($sku, $productData);
        } catch (Exception $e) {
            Log::error('Update Amazon Product Inventory Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get user's Amazon orders
     */
    public function getOrders($createdAfter = null, $nextToken = null)
    {
        try {
            $queryParams = [
                // 'OrderStatuses' => 'PendingAvailability,Unshipped,Pending,PartiallyShipped,Shipped,Canceled,Unfulfillable,InvoiceUnconfirmed'
            ];

            if ($createdAfter) {
                $queryParams['CreatedAfter'] = $createdAfter;
            }

            if ($nextToken) {
                $queryParams['NextToken'] = $nextToken;
            }

            $endpoint = "/orders/v0/orders";

            return $this->makeAmazonRequest('get', $endpoint, [], $queryParams);
        } catch (Exception $e) {
            Log::error('Get Amazon Orders Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get specific order by ID
     */
    public function getOrder($orderId)
    {
        try {
            $endpoint = "/orders/v0/orders/{$orderId}";
            return $this->makeAmazonRequest('get', $endpoint);
        } catch (Exception $e) {
            Log::error('Get Amazon Order Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get order items
     */
    public function getOrderItems($orderId, $nextToken = null)
    {
        try {
            $queryParams = [];

            if ($nextToken) {
                $queryParams['NextToken'] = $nextToken;
            }

            $endpoint = "/orders/v0/orders/{$orderId}/orderItems";

            return $this->makeAmazonRequest('get', $endpoint, [], $queryParams);
        } catch (Exception $e) {
            Log::error('Get Amazon Order Items Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Upload product image
     */
    public function uploadProductImage($sku, $imageUrl, $imageType = 'MAIN')
    {
        try {
            $productData = [
                'attributes' => [
                    'images' => [
                        [
                            'link' => $imageUrl,
                            'height' => 500,
                            'width' => 500,
                            'variant' => $imageType
                        ]
                    ]
                ]
            ];

            return $this->upsertProduct($sku, $productData);
        } catch (Exception $e) {
            Log::error('Upload Amazon Product Image Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Create full product listing with all details
     */
    public function createFullProduct($sku, $productData)
    {
        try {
            $formattedData = [
                'attributes' => [
                    'title' => Arr::get($productData, 'title'),
                    'product_type' => Arr::get($productData, 'product_type'),
                    'brand' => Arr::get($productData, 'brand'),
                    'description' => Arr::get($productData, 'description'),
                    'bullet_points' => Arr::get($productData, 'bullet_points', []),
                    'item_package_dimensions' => [
                        'length' => [
                            'value' => Arr::get($productData, 'dimensions.length', 1),
                            'unit' => 'inches'
                        ],
                        'width' => [
                            'value' => Arr::get($productData, 'dimensions.width', 1),
                            'unit' => 'inches'
                        ],
                        'height' => [
                            'value' => Arr::get($productData, 'dimensions.height', 1),
                            'unit' => 'inches'
                        ],
                        'weight' => [
                            'value' => Arr::get($productData, 'dimensions.weight', 1),
                            'unit' => 'pounds'
                        ]
                    ],
                    'fulfillment_availability' => [
                        [
                            'quantity' => Arr::get($productData, 'quantity', 0),
                            'fulfillment_channel_code' => 'DEFAULT'
                        ]
                    ],
                    'price' => [
                        'value' => Arr::get($productData, 'price', 0),
                        'currency' => Arr::get($productData, 'currency', 'USD')
                    ]
                ]
            ];

            // Add images if present
            $images = Arr::get($productData, 'images', []);

            if (is_array($images)) {
                $formattedData['attributes']['images'] = [];

                foreach ($images as $index => $imageUrl) {
                    $imageType = $index === 0 ? 'MAIN' : 'PT' . $index;

                    $formattedData['attributes']['images'][] = [
                        'link' => $imageUrl,
                        'height' => 500,
                        'width' => 500,
                        'variant' => $imageType
                    ];
                }
            }

            return $this->upsertProduct($sku, $formattedData);
        } catch (Exception $e) {
            Log::error('Create Full Amazon Product Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get account info
     */
    public function getSellerAccount()
    {
        try {
            $endpoint = "/sellers/v1/account";
            return $this->makeAmazonRequest('get', $endpoint);
        } catch (Exception $e) {
            Log::error('Get Amazon Account Info Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}
