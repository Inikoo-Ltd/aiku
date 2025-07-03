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
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Str;

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
            'redirect_uri' => route('retina.dropshipping.platform.amazon.callback'),
            'region' => config('services.amazon.region'),
            'sandbox' => false,
            'access_token' => Arr::get($this->settings, 'credentials.access_token'),
            'refresh_token' => Arr::get($this->settings, 'credentials.refresh_token'),
            'expires_in' => Arr::get($this->settings, 'credentials.expires_in'),
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
            $config['access_token'] = Arr::get($this->settings, 'credentials.access_token');
            $config['refresh_token'] = Arr::get($this->settings, 'credentials.refresh_token');
        }*/

        return $config;
    }

    /**
     * Get Amazon OAuth token endpoint URL
     */
    protected function getAmazonTokenUrl()
    {
        $config = $this->getAmazonConfig();
        return $config['sandbox']
            ? 'https://api.sandbox.amazon.com/auth/o2/token'
            : 'https://api.amazon.com/auth/o2/token';
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
            'redirect_uri' => $config['redirect_uri'],
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
        ])->withToken($token)
            ->get("{$this->getAmazonBaseUrl()}/sellers/v1/marketplaceParticipations");

        if ($res->successful()) {
            $data = $res->json();

            if (isset($data['payload']) && is_array($data['payload']) && count($data['payload']) > 0) {
                $marketplaceId = Arr::get($data, 'payload.0.marketplace.id');

                UpdateAmazonUser::run($this, [
                    'settings' => [
                        'credentials' => [
                            ...Arr::get($this->settings, 'credentials', []),
                            'marketplace_id' => $marketplaceId
                        ]
                    ]
                ]);

                return $marketplaceId;
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
                UpdateAmazonUser::run($this, [
                    'settings' => [
                        'credentials' => [
                            'access_token' => $tokenData['access_token'],
                            'refresh_token' => $tokenData['refresh_token'],
                            'expires_in' => now()->addSeconds($tokenData['expires_in'])
                        ]
                    ]
                ]);

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
                UpdateAmazonUser::run($this, [
                    'settings' => [
                        'credentials' => [
                            'access_token' => $tokenData['access_token'],
                            'expires_in' => now()->addSeconds($tokenData['expires_in']),
                            'refresh_token' => $config['refresh_token']
                        ]
                    ]
                ]);

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
        $expiresAt = Arr::get($this->settings, 'credentials.expires_in');

        // Try to refresh token if we have a refresh token
        if ($config['refresh_token']) {
            $tokenData = $this->refreshAmazonToken();

            UpdateAmazonUser::run($this, [
                'settings' => [
                    'credentials' => [
                        ...Arr::get($this->settings, 'credentials', []),
                        'access_token' => $tokenData['access_token'],
                        'expires_in' => now()->addSeconds($tokenData['expires_in'])
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
        return !empty(Arr::get($this->settings, 'credentials.access_token')) &&
            !empty(Arr::get($this->settings, 'credentials.refresh_token'));
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
    public function getLWAAccessToken($config = null)
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
     * Make authenticated request to Amazon SP-API
     */
    protected function makeAmazonRequest($method, $endpoint, $data = [], $queryParams = [])
    {
        try {
            $url = $this->getAmazonBaseUrl() . $endpoint;
            $config = $this->getAmazonConfig();
            $token = $this->getAmazonAccessToken();

            if (! isset($queryParams['marketplaceIds'])) {
                if (!isset($queryParams['MarketplaceIds']) && !isset($queryParams['MarketplaceId'])) {
                    $queryParams['MarketplaceIds'] = $config['marketplace_id'];
                }
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
            $sellerId = Arr::get($this->data, 'seller.id');
            $endpoint = "/listings/2021-08-01/items/{$sellerId}/{$sku}";
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
            $config = $this->getAmazonConfig();
            $sellerId = Arr::get($this->data, 'seller.id');
            $endpoint = "/listings/2021-08-01/items/{$sellerId}/{$sku}";

            return $this->makeAmazonRequest('put', $endpoint, $productData, [
                'marketplaceIds' => $config['marketplace_id'],
                'includedData' => 'identifiers,issues',
                'mode' => 'VALIDATION_PREVIEW'
            ]);
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
            $sellerId = Arr::get($this->data, 'seller.id');
            $endpoint = "/listings/2021-08-01/items/{$sellerId}/{$sku}";
            $queryParams = [];

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
                'OrderStatuses' => 'PendingAvailability,Unshipped,Pending,PartiallyShipped,Shipped,Canceled,Unfulfillable,InvoiceUnconfirmed'
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
            $config = $this->getAmazonConfig();
            $marketplaceId = $config['marketplace_id'];

            $formattedData = [
                "productType" => "ACCESSORY",
                "requirements" => "LISTING",
                "attributes" => [
                    "condition_type" => [
                        [
                            "value" => "new_new",
                            "marketplace_id" => $marketplaceId
                        ]
                    ],
                    "item_name" => [
                        [
                            "value" => Arr::get($productData, 'title'),
                            "marketplace_id" => $marketplaceId
                        ]
                    ],
                    "brand" => [
                        [
                            "value" => 'Generic',
                            "marketplace_id" => $marketplaceId
                        ]
                    ],
/*                    "recommended_browse_nodes" => [
                        [
                            "value" => "281407",
                            "marketplace_id" => $marketplaceId
                        ]
                    ],*/
                    "merchant_suggested_asin" => [
                        [
                            "value" => Str::substr('ASIN-'.Arr::get($productData, 'id'), 0, 10)
                        ]
                    ],
                    "product_description" => [
                        [
                            "value" => Arr::get($productData, 'description'),
                            "marketplace_id" => $marketplaceId
                        ]
                    ],
                    "item_type_keyword" => [
                        [
                            "value" => "accessory",
                            "marketplace_id" => $marketplaceId
                        ]
                    ],
                    "bullet_point" => [
                        [
                            "value" => "High quality material",
                            "marketplace_id" => $marketplaceId
                        ],
                        [
                            "value" => "Sleek and durable design",
                            "marketplace_id" => $marketplaceId
                        ]
                    ],
                    "generic_keyword" => [
                        [
                            "value" => "accessory, durable, daily use",
                            "marketplace_id" => $marketplaceId
                        ]
                    ],
                    "care_instructions" => [
                        [
                            "value" => "Machine wash cold, tumble dry low",
                            "marketplace_id" => $marketplaceId
                        ]
                    ],
                    "item_package_weight" => [
                        [
                            "value" => 0.5,
                            "unit" => "pounds",
                            "marketplace_id" => $marketplaceId
                        ]
                    ],
                    "fabric_type" => [
                        [
                            "value" => "100% Original",
                            "marketplace_id" => $marketplaceId
                        ]
                    ],
                    "target_gender" => [
                        [
                            "value" => "unisex",
                            "marketplace_id" => $marketplaceId
                        ]
                    ],
                    "department" => [
                        [
                            "value" => "unisex-adult",
                            "marketplace_id" => $marketplaceId
                        ]
                    ],
                    "size" => [
                        [
                            "value" => "Medium",
                            "marketplace_id" => $marketplaceId
                        ]
                    ],
                    "import_designation" => [
                        [
                            "value" => "imported",
                            "marketplace_id" => $marketplaceId
                        ]
                    ],
                    "age_range_description" => [
                        [
                            "value" => "Adult",
                            "marketplace_id" => $marketplaceId
                        ]
                    ],
                    "batteries_required" => [
                        [
                            "value" => false,
                            "marketplace_id" => $marketplaceId
                        ]
                    ],
                    "model_name" => [
                        [
                            "value" => 'Accessory Original',
                            "marketplace_id" => $marketplaceId
                        ]
                    ],
                    "list_price" => [
                        [
                            "value" => Arr::get($productData, 'price'),
                            "marketplace_id" => $marketplaceId
                        ]
                    ],
                    'purchasable_offer' => [
                        [
                            'marketplace_id' => $marketplaceId,
                            'currency' => 'USD',
                            'our_price' => [
                                [
                                    'schedule' => [
                                        [
                                            'value_with_tax' => Arr::get($productData, 'price')
                                        ]
                                    ]
                                ]
                            ],
                            'quantity' => Arr::get($productData, 'quantity')
                        ]
                    ],

                    "supplier_declared_dg_hz_regulation" => [
                        [
                            "value" => "not_applicable",
                            "marketplace_id" => $marketplaceId
                        ]
                    ],
                    "item_package_dimensions" => [
                        [
                            "length" => [
                                "value" => 10,
                                "unit" => "inches"
                            ],
                            "width" => [
                                "value" => 6,
                                "unit" => "inches"
                            ],
                            "height" => [
                                "value" => 2,
                                "unit" => "inches"
                            ]
                        ]
                    ],
                    "color" => [
                        [
                            "value" => "Black",
                            "marketplace_id" => $marketplaceId
                        ]
                    ],
                    "country_of_origin" => [
                        [
                            "value" => "US",
                            "marketplace_id" => $marketplaceId
                        ]
                    ],
                    'fulfillment_availability' => [
                        [
                            "quantity" => Arr::get($productData, 'quantity'),
                            'fulfillment_channel_code' => 'DEFAULT', // or 'AMAZON' for FBA
                            'marketplace_id' => $marketplaceId
                        ]
                    ],
                ]
            ];


            // Add images if present
            $images = Arr::get($productData, 'images', []);

            //            if (is_array($images)) {
            //                $formattedData['attributes']['images'] = [];
            //
            //                foreach ($images as $index => $imageUrl) {
            //                    $imageType = $index === 0 ? 'MAIN' : 'PT' . $index;
            //
            //                    $formattedData['attributes']['images'][] = [
            //                        'link' => $imageUrl,
            //                        'height' => 500,
            //                        'width' => 500,
            //                        'variant' => $imageType
            //                    ];
            //                }
            //            }

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

    /**
     * Confirm shipment for an order
     */
    public function confirmShipment($orderId, $shipmentData)
    {
        try {
            $endpoint = "/orders/v0/orders/{$orderId}/shipmentConfirmation";
            $data = [
                'marketplaceId' => $this->getAmazonConfig()['marketplace_id'],
                'packageDetail' => [
                    'packageReferenceId' => Arr::get($shipmentData, 'id'),
                    'carrierCode' => 'Other',
                    'carrierName' => Arr::get($shipmentData, 'name'),
                    'trackingNumber' => Arr::get($shipmentData, 'tracking'),
                    'shipDate' => now(),
                    'orderItems' => Arr::get($shipmentData, 'items')
                ]
            ];

            return $this->makeAmazonRequest('post', $endpoint, $data);
        } catch (Exception $e) {
            Log::error('Confirm Amazon Shipment Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}
