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
            $config = $this->getAmazonConfig();
            $endpoint = "/catalog/2022-04-01/items/{$asin}";
            return $this->makeAmazonRequest('get', $endpoint, [], [
                'marketplaceIds' => $config['marketplace_id'],
                'includedData' => 'attributes,productTypes,classifications,dimensions,summaries'
            ]);
        } catch (Exception $e) {
            Log::error('Get Amazon Product Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get specific product by EAN
     */
    public function getProductByEan($ean)
    {
        try {
            $config = $this->getAmazonConfig();

            $endpoint = "/catalog/2022-04-01/items";
            return $this->makeAmazonRequest('get', $endpoint, [], [
                'marketplaceIds' => $config['marketplace_id'],
                'identifiersType' => 'EAN',
                'identifiers' => $ean
            ]);
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
                'includedData' => 'issues',
                // 'mode' => 'VALIDATION_PREVIEW'
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
    public function createFullProduct($productType, $productInfo, $exisingAttributes)
    {
        try {
            $config = $this->getAmazonConfig();

            $sku = $productAttributes['sku'] ?? 'SKU_' . $productInfo['asin'] . '_' . time();

            // Determine product type based on website display group
            // $productType = $this->determineProductType($productInfo['website_display_group'] ?? '');

            // Prepare the listing payload
            $productAttributes = $this->buildProductAttributes($productInfo, $exisingAttributes);

            $payload = [
                'productType' => $productType,
                'requirements' => 'LISTING',
                'attributes' => $productAttributes // $this->buildProductAttributes($productInfo)
            ];

            return $this->upsertProduct($sku, $payload);
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
     * Get account info
     */
    public function getProductCategoriesByAsin($asin)
    {
        try {
            $config =  $this->getAmazonConfig();
            $endpoint = "/catalog/v0/categories";
            return $this->makeAmazonRequest('get', $endpoint, [], [
                'ASIN' => $asin,
                'MarketplaceId' => $config['marketplace_id']
            ]);
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

    public function createProductFromSearchData($searchResults, $additionalData = [])
    {
        try {
            // Extract the first item from search results
            $item = $searchResults['items'][0] ?? null;

            if (!$item) {
                throw new Exception('No items found in search results');
            }

            // Extract product information
            $productInfo = $this->extractProductInfo($item);

            // Merge with additional data if provided
            $productInfo = array_merge($productInfo, $additionalData);

            $product = $this->getProductByAsin($productInfo['asin']);
            $attributes = $product['attributes'];

            // Create the product listing
            return $this->createFullProduct(Arr::get($product, 'productTypes.0.productType'), $productInfo, $attributes);

        } catch (Exception $e) {
            Log::error('Error creating product from search data: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extract product information from search results
     */
    protected function extractProductInfo($item)
    {
        $config =  $this->getAmazonConfig();
        $summaries = $item['summaries'] ?? [];
        $firstSummary = $summaries[0] ?? [];

        return [
            'asin' => $item['asin'] ?? '',
            'marketplace_id' => $firstSummary['marketplaceId'] ?? $config['marketplace_id'],
            'title' => $firstSummary['itemName'] ?? '',
            'brand' => $firstSummary['brand'] ?? '',
            'manufacturer' => $firstSummary['manufacturer'] ?? $firstSummary['brand'] ?? '',
            'model_number' => $firstSummary['modelNumber'] ?? '',
            'part_number' => $firstSummary['partNumber'] ?? '',
            'package_quantity' => $firstSummary['packageQuantity'] ?? 1,
            'item_classification' => $firstSummary['itemClassification'] ?? 'BASE_PRODUCT',
            'trade_in_eligible' => $firstSummary['tradeInEligible'] ?? false,
            'adult_product' => $firstSummary['adultProduct'] ?? false,
            'autographed' => $firstSummary['autographed'] ?? false,
            'memorabilia' => $firstSummary['memorabilia'] ?? false,
            'website_display_group' => $firstSummary['websiteDisplayGroup'] ?? '',
            'website_display_group_name' => $firstSummary['websiteDisplayGroupName'] ?? '',
        ];
    }

    /**
     * Build product attributes for the listing
     */
    protected function buildProductAttributes($productInfo, $existingAttributes)
    {
        $attributes = [
            // Core product identifiers
            'condition_type' => [
                [
                    'value' => 'new_new'
                ]
            ],

            // Product title
            'item_name' => [
                [
                    'value' => $productInfo['title'],
                    'language_tag' => 'en_US'
                ]
            ],

            // Brand
            'brand' => [
                [
                    'value' => $productInfo['brand']
                ]
            ],

            // Manufacturer
            'manufacturer' => [
                [
                    'value' => $productInfo['manufacturer']
                ]
            ],

            // Model number
            'model_number' => [
                [
                    'value' => $productInfo['model_number']
                ]
            ],

            // Part number
            'part_number' => [
                [
                    'value' => $productInfo['part_number']
                ]
            ],

            // Package quantity
            'unit_count' => [
                [
                    'value' => $productInfo['package_quantity'] ?? 10,
                    'type' => [
                        'value' => 'item',
                        'language_tag' => 'en_US'
                    ]
                ]
            ],

            /*'contains_liquid_contents' => [
                ['value' => false] // or 'true' if your product contains liquids
            ],*/

            'item_width_height' => [
                ['width' => [
                    'value' => 6,
                    'unit' => 'inches'
                ], 'height' => [
                    'value' => 6,
                    'unit' => 'inches'
                ]]
            ],
            'material' => [
                ['value' => 'Plastic']
            ],
            'specific_uses_for_product' => [
                ['value' => 'Office, Home']
            ],
            'product_description' => [
                ['value' => 'A high-quality ergonomic mouse with smooth tracking and durable design.']
            ],
            'number_of_items' => [
                ['value' => '1']
            ],
            'warranty_description' => [
                ['value' => 'No warranty']
            ],
            'model_name' => [
                ['value' => 'MOUSE-001X']
            ],

            'country_of_origin' => [
                ['value' => 'US'] // Change to actual country (US, CN, DE, etc.)
            ],
            "supplier_declared_dg_hz_regulation" => [
                [
                    "value" => "not_applicable"
                ]
            ],
            /*"item_package_dimensions" => [
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
            ],*/

            'color' => [
                ['value' => $productInfo['color'] ?? 'Multi-Color']
            ],

            "item_package_weight" => [
                [
                    "value" => 0.5,
                    "unit" => "pounds"
                ]
            ],

            'list_price' => [
                ['value' => 10]
            ],

            'batteries_required' => [
                ['value' => false] // or 'true' if batteries are required
            ],

            'fulfillment_availability' => [
                [
                    "quantity" => 100,
                    'fulfillment_channel_code' => 'DEFAULT', // or 'AMAZON' for FBA
                ]
            ],

            'purchasable_offer' => [
                [
                    'currency' => 'USD',
                    'our_price' => [
                        [
                            'schedule' => [
                                [
                                    'value_with_tax' => Arr::get($productInfo, 'price', 1)
                                ]
                            ]
                        ]
                    ],
                    'quantity' => Arr::get($productInfo, 'quantity', 1)
                ]
            ],

            /*'is_heat_sensitive' => [
                ['value' => false] // or 'true' if heat sensitive
            ],*/

            'item_type_keyword' => [
                ['value' => $productInfo['category'] ?? 'general']
            ],
        ];

        // Add ASIN reference if available
        if (!empty($productInfo['asin'])) {
            $attributes['externally_assigned_product_identifier'] = [
                ['value' => $productInfo['asin']]
            ];

            $attributes['merchant_suggested_asin'] = [
                ['value' => $productInfo['asin']]
            ];
        }

        // Add additional attributes based on product type
        return array_merge($attributes, $existingAttributes);
    }

    /**
     * Determine product type based on website display group
     */
    protected function determineProductType($websiteDisplayGroup)
    {
        $mappings = [
            'kitchen_display_on_website' => 'KITCHEN',
            'health_and_beauty_display_on_website' => 'HEALTH_PERSONAL_CARE',
            'beauty_display_on_website' => 'BEAUTY',
            'grocery_display_on_website' => 'GROCERY',
            'baby_display_on_website' => 'BABY_PRODUCT',
            'sports_display_on_website' => 'SPORTING_GOODS',
            'home_garden_display_on_website' => 'HOME_AND_GARDEN',
            'electronics_display_on_website' => 'ELECTRONICS',
        ];

        return $mappings[$websiteDisplayGroup] ?? 'HEALTH_PERSONAL_CARE';
    }

    /**
     * Get product type specific attributes
     */
    protected function getProductTypeSpecificAttributes($productInfo)
    {
        $attributes = [];

        // Add generic product description
        $attributes['product_description'] = [
            [
                'value' => $this->generateProductDescription($productInfo),
                'language_tag' => 'en_US'
            ]
        ];

        // Add bullet points
        $attributes['bullet_point'] = $this->generateBulletPoints($productInfo);

        return $attributes;
    }

    /**
     * Generate product description
     */
    protected function generateProductDescription($productInfo)
    {
        $description = "High-quality {$productInfo['title']} from {$productInfo['brand']}.";

        if ($productInfo['model_number']) {
            $description .= " Model: {$productInfo['model_number']}.";
        }

        if ($productInfo['package_quantity'] > 1) {
            $description .= " Package contains {$productInfo['package_quantity']} units.";
        }

        return $description;
    }

    /**
     * Generate bullet points
     */
    protected function generateBulletPoints($productInfo)
    {
        $bulletPoints = [];

        $bulletPoints[] = [
            'value' => "Brand: {$productInfo['brand']}",
            'language_tag' => 'en_US'
        ];

        if ($productInfo['model_number']) {
            $bulletPoints[] = [
                'value' => "Model Number: {$productInfo['model_number']}",
                'language_tag' => 'en_US'
            ];
        }

        if ($productInfo['package_quantity'] > 1) {
            $bulletPoints[] = [
                'value' => "Package Quantity: {$productInfo['package_quantity']} units",
                'language_tag' => 'en_US'
            ];
        }

        if ($productInfo['manufacturer']) {
            $bulletPoints[] = [
                'value' => "Manufactured by: {$productInfo['manufacturer']}",
                'language_tag' => 'en_US'
            ];
        }

        return $bulletPoints;
    }
}
