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
    public int $timeOut = 30;

    public function setTimeout(int $timeOut): void
    {
        $this->timeOut = $timeOut;
    }

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
            'includes'   => 'contains',
            'include'    => 'contain',
            'javascript' => '',
            '.cookie'    => '',
            'cookie('    => '',
            'replace('   => '',
            'IFRAME'     => '',
            'META'       => '',
            'base href'  => '',
        ];

        $text = str_ireplace(array_keys($problematic), array_values($problematic), $text);

        return preg_replace('/\b(includes?|javascript)\b/i', '', $text);
    }

    /**
     * Check if an error requires user action
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
            // Check if the error has parameters with field names
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

            // Check error-category-REQUEST errors often need user action
            if (isset($error['category']) && $error['category'] === 'REQUEST') {
                // Check if a message mentions any actionable fields
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

        $errors        = is_string($errorResponse) ? json_decode($errorResponse, true) : $errorResponse;
        $displayErrors = [];

        foreach ($errors['errors'] as $error) {
            $fieldName    = null;
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

            // Try to extract a field from an error message
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

    public function extractProductAttributes($product, $categoryAspects)
    {
        $attributes = [];
        $brand = $product->getBrand();

        // Get required aspects from a category
        $requiredAspects = collect($categoryAspects['aspects'] ?? [])
            ->filter(fn ($aspect) => $aspect['aspectConstraint']['aspectRequired'] ?? false);

        foreach ($requiredAspects as $aspect) {
            $aspectName = $aspect['localizedAspectName'];

            // Map your product data to eBay aspects
            switch ($aspectName) {
                case 'Style':
                    // Try to get from product attributes or use default
                    $attributes['Style'] = $product->style ??
                        $product->attributes['style'] ??
                        ['Not Specified'];
                    break;
                case 'Brand':
                    $attributes['Brand'] = [$brand?->name ?? $product->shop?->name ?? 'Unbranded'];
                    break;
                case 'Department':
                    $attributes['Department'] = ['Unisex Adults'];
                    break;
                case 'EAN':
                    $attributes['EAN'] = [$product->barcode];
                    break;
                    // Add more mappings as needed
                default:
                    // Use generic mapping or default value
                    $attributes[$aspectName] = [$this->getDefaultValueForAspect($aspect)];
            }
        }

        return $attributes;
    }

    public function getDefaultValueForAspect($aspect)
    {
        // Return the first recommended value or "Not Specified"
        return $aspect['aspectValues'][0]['localizedValue'] ?? ['Not Specified'];
    }

    public function parseMissingAspects($errorMessage)
    {
        // Extract the aspect name from an error message
        preg_match('/item specific (\w+)/', $errorMessage, $matches);

        return $matches[1] ?? null;
    }

    public function getItemAspectsForCategory($categoryId)
    {
        $marketplace = Arr::get($this->getEbayConfig(), 'marketplace_id');

        $categoryTree = match ($marketplace) {
            'EBAY_ES' => 186,
            'EBAY_DE' => 77,
            default => 3
        };

        try {
            $endpoint = "/commerce/taxonomy/v1/category_tree/$categoryTree/get_item_aspects_for_category";

            return $this->makeEbayRequest('get', $endpoint, [
                'category_id' => $categoryId
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to get eBay category aspects', [
                'category_id' => $categoryId,
                'error'       => $e->getMessage()
            ]);

            return ['aspects' => []]; // Return empty aspects on failure
        }
    }

    public function getRequiredItemAspectsForCategory($categoryId)
    {
        return collect(Arr::get($this->getItemAspectsForCategory($categoryId), 'aspects', []))->filter(function ($aspect, $key) {
            return $aspect['aspectConstraint']['aspectRequired'] === true;
        })->map(function ($aspect) {
            return [
                'name' => $aspect['localizedAspectName'],
                'dataType' => $aspect['aspectConstraint']['aspectDataType'],
                'mode' => $aspect['aspectConstraint']['aspectMode'],
                'cardinality' => $aspect['aspectConstraint']['itemToAspectCardinality']
            ];
        })->values();
    }

    public function getServicesWithCarrierInfo(): array
    {
        $marketplace = Arr::get($this->getEbayConfig(), 'marketplace_id');

        $services = [
            'EBAY_GB' => [
                'UK_OtherCourier'     => [
                    'service_code' => 'UK_OtherCourier',
                    'service_name' => 'Yodel',
                    'carrier_code' => 'Yodel',
                    'carrier_name' => 'Yodel',
                ],
                'UK_RoyalMailNextDay' => [
                    'service_code' => 'UK_RoyalMailNextDay',
                    'service_name' => 'Royal Mail',
                    'carrier_code' => 'RoyalMail',
                    'carrier_name' => 'Royal Mail',
                ]
            ],
            'EBAY_DE' => [
                'DE_DHLPaket'              => [
                    'service_code' => 'DE_DHLPaket',
                    'service_name' => 'DHL Paket',
                    'carrier_code' => 'DHL',
                    'carrier_name' => 'DHL',
                ],
                'DE_DHLPaketTracked'       => [
                    'service_code' => 'DE_DHLPaketTracked',
                    'service_name' => 'DHL Paket mit Sendungsverfolgung',
                    'carrier_code' => 'DHL',
                    'carrier_name' => 'DHL',
                ],
                'DE_DHLPaketInsured'       => [
                    'service_code' => 'DE_DHLPaketInsured',
                    'service_name' => 'DHL Paket versichert',
                    'carrier_code' => 'DHL',
                    'carrier_name' => 'DHL',
                ],
                'DE_DHLPackchen'           => [
                    'service_code' => 'DE_DHLPackchen',
                    'service_name' => 'DHL Päckchen',
                    'carrier_code' => 'DHL',
                    'carrier_name' => 'DHL',
                ],
                'DE_HermesPackchen'        => [
                    'service_code' => 'DE_HermesPackchen',
                    'service_name' => 'Hermes Päckchen',
                    'carrier_code' => 'HERMES',
                    'carrier_name' => 'Hermes',
                ],
                'DE_HermesPaket'           => [
                    'service_code' => 'DE_HermesPaket',
                    'service_name' => 'Hermes Paket',
                    'carrier_code' => 'HERMES',
                    'carrier_name' => 'Hermes',
                ],
                'DE_HermesPaketVersichert' => [
                    'service_code' => 'DE_HermesPaketVersichert',
                    'service_name' => 'Hermes Paket versichert',
                    'carrier_code' => 'HERMES',
                    'carrier_name' => 'Hermes',
                ],
                'DE_HermesSperrgut'        => [
                    'service_code' => 'DE_HermesSperrgut',
                    'service_name' => 'Hermes Sperrgut',
                    'carrier_code' => 'HERMES',
                    'carrier_name' => 'Hermes',
                ],
                'DE_DPD'                   => [
                    'service_code' => 'DE_DPD',
                    'service_name' => 'DPD',
                    'carrier_code' => 'DPD',
                    'carrier_name' => 'DPD',
                ],
                'DE_DPDExpress'            => [
                    'service_code' => 'DE_DPDExpress',
                    'service_name' => 'DPD Express',
                    'carrier_code' => 'DPD',
                    'carrier_name' => 'DPD',
                ],
                'DE_GLS'                   => [
                    'service_code' => 'DE_GLS',
                    'service_name' => 'GLS',
                    'carrier_code' => 'GLS',
                    'carrier_name' => 'GLS',
                ],
                'DE_GLSExpress'            => [
                    'service_code' => 'DE_GLSExpress',
                    'service_name' => 'GLS Express',
                    'carrier_code' => 'GLS',
                    'carrier_name' => 'GLS',
                ],
                'DE_UPS'                   => [
                    'service_code' => 'DE_UPS',
                    'service_name' => 'UPS',
                    'carrier_code' => 'UPS',
                    'carrier_name' => 'UPS',
                ],
                'DE_FedEx'                 => [
                    'service_code' => 'DE_FedEx',
                    'service_name' => 'FedEx',
                    'carrier_code' => 'FEDEX',
                    'carrier_name' => 'FedEx',
                ],
                'DE_DeutschePost'          => [
                    'service_code' => 'DE_DeutschePost',
                    'service_name' => 'Deutsche Post',
                    'carrier_code' => 'DEUTSCHEPOST',
                    'carrier_name' => 'Deutsche Post',
                ],
                'DE_DeutschePostBrief'     => [
                    'service_code' => 'DE_DeutschePostBrief',
                    'service_name' => 'Deutsche Post Brief',
                    'carrier_code' => 'DEUTSCHEPOST',
                    'carrier_name' => 'Deutsche Post',
                ],
                'DE_Abholung'              => [
                    'service_code' => 'DE_Abholung',
                    'service_name' => 'Abholung (Collection)',
                    'carrier_code' => 'OTHER',
                    'carrier_name' => 'Sonstige (Other)',
                ],
                'DE_Sonstige'              => [
                    'service_code' => 'DE_Sonstige',
                    'service_name' => 'Sonstige (Other)',
                    'carrier_code' => 'OTHER',
                    'carrier_name' => 'Sonstige (Other)',
                ],
            ],
            'EBAY_ES' => [
                'ES_Correos'            => [
                    'service_code' => 'ES_Correos',
                    'service_name' => 'Correos',
                    'carrier_code' => 'CORREOS',
                    'carrier_name' => 'Correos',
                ],
                'ES_CorreosPaqueteAzul' => [
                    'service_code' => 'ES_CorreosPaqueteAzul',
                    'service_name' => 'Correos Paquete Azul',
                    'carrier_code' => 'CORREOS',
                    'carrier_name' => 'Correos',
                ],
                'ES_CorreosExpress'     => [
                    'service_code' => 'ES_CorreosExpress',
                    'service_name' => 'Correos Express',
                    'carrier_code' => 'CORREOSEXPRESS',
                    'carrier_name' => 'Correos Express',
                ],
                'ES_SEUR'               => [
                    'service_code' => 'ES_SEUR',
                    'service_name' => 'SEUR',
                    'carrier_code' => 'SEUR',
                    'carrier_name' => 'SEUR',
                ],
                'ES_Other'              => [
                    'service_code' => 'ES_Other',
                    'service_name' => 'Otro (Other)',
                    'carrier_code' => 'OTHER',
                    'carrier_name' => 'Otro (Other)',
                ],
            ],
        ];

        return $services[$marketplace] ?? $services['EBAY_GB'];
    }

    public function defaultCarrier(): array
    {
        return [
            'EBAY_GB' => [
                'service_code' => 'UK_OtherCourier',
                'service_name' => 'Yodel',
                'carrier_code' => 'Yodel',
                'carrier_name' => 'Yodel',
            ],
            'EBAY_DE' => [
                'service_code' => 'DE_Sonstige',
                'service_name' => 'Sonstige (Other)',
                'carrier_code' => 'Other',
                'carrier_name' => 'Other',
            ],
            'EBAY_ES' => [
                'service_code' => 'ES_Other',
                'service_name' => 'Otro (Other)',
                'carrier_code' => 'Other',
                'carrier_name' => 'Otro (Other)',
            ]
        ];
    }

    public function getServicesForOptions(): array
    {
        return array_map(function ($service) {
            return $service['service_name'];
        }, $this->getServicesWithCarrierInfo());
    }

    /**
     * eBay API configuration
     *
     * @throws \Exception
     */
    protected function getEbayConfig(): array
    {
        $shop = $this->shop ?? $this->customer?->shop ?? $this->customerSalesChannel?->shop;
        if ($shop === null) {
            throw new Exception('Shop not found');
        }

        $marketplace = $this->marketplace ?? Arr::get($shop->settings, 'ebay.marketplace_id');

        return [
            'client_id'      => config('services.ebay.client_id'),
            'client_secret'  => config('services.ebay.client_secret'),
            'redirect_uri'   => Arr::get($shop->settings, 'ebay.redirect_key'),
            'sandbox'        => config('services.ebay.sandbox'),
            'access_token'   => Arr::get($this->settings, 'credentials.ebay_access_token'),
            'refresh_token'  => Arr::get($this->settings, 'credentials.ebay_refresh_token'),
            'marketplace_id' => $marketplace,
            'currency'       => match ($marketplace) {
                'EBAY_ES', 'EBAY_DE' => 'EUR',
                default => $shop->currency?->code ?? 'GBP'
            }
        ];
    }

    /**
     * Get eBay OAuth token endpoint URL
     *
     * @throws \Exception
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
     *
     * @throws \Exception
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
     *
     * @throws \Exception
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
     *
     * @throws \Exception
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
            'client_id'     => $config['client_id'],
            'redirect_uri'  => $config['redirect_uri'],
            'response_type' => 'code',
            'scope'         => implode(' ', $scopes),
        ];

        if ($state) {
            $params['state'] = $state;
        }

        $queryString = http_build_query($params);


        return $this->getEbayOAuthUrl()."/oauth2/authorize?$queryString";
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
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic '.base64_encode($config['client_id'].':'.$config['client_secret'])
            ])->post($this->getEbayTokenUrl(), [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $config['refresh_token'],
                'scope'         => implode(' ', $scopes),
            ]);

            if ($response->successful()) {
                $tokenData = $response->json();

                // Update stored tokens
                UpdateEbayUser::run($this, [
                    'settings' => [
                        'credentials' => [
                            'ebay_access_token'     => $tokenData['access_token'],
                            'ebay_refresh_token'    => $config['refresh_token'],
                            'ebay_token_expires_at' => now()->addSeconds($tokenData['expires_in'])
                        ]
                    ]
                ]);

                return $tokenData;
            }
        } catch (Exception $e) {
            Log::error('eBay Token Refresh Error: '.$e->getMessage());
        }

        return null;
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

        return null;
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
                    'Authorization' => 'Basic '.base64_encode($config['client_id'].':'.$config['client_secret'])
                ])->post($this->getEbayOAuthUrl().'/identity/v1/oauth2/revoke', [
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
            Log::error('eBay Token Revocation Error: '.$e->getMessage());

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
            $url   = $this->getEbayBaseUrl().$endpoint;
            $marketplaceId = Arr::get($this->getEbayConfig(), 'marketplace_id');

            $contentLanguage = match ($marketplaceId) {
                'EBAY_DE' => 'de-DE',
                'EBAY_ES' => 'es-ES',
                default => 'en-GB'
            };

            $response = Http::withHeaders([
                'Authorization'    => 'Bearer '.$token,
                'Content-Type'     => 'application/json',
                'Accept'           => 'application/json',
                'Content-Language' => $contentLanguage
            ])->withQueryParameters($queryParams)
                ->$method(
                    $url,
                    $data
                );

            if ($response->successful()) {
                return $response->json();
            }

            // If unauthorized, try to refresh the token once
            if ($response->status() === 401) {
                $token    = $this->refreshEbayToken()['access_token'];
                $response = Http::withHeaders([
                    'Authorization'    => 'Bearer '.$token,
                    'Content-Type'     => 'application/json',
                    'Accept'           => 'application/json',
                    'Content-Language' => 'en-GB'
                ])->$method(
                    $url,
                    $data
                );

                if ($response->successful()) {
                    return $response->json();
                }
            } else {
                return $response->json();
            }
        } catch (Exception $e) {
            Log::error('eBay Token Error: '.$e->getMessage());

            return [$e->getMessage()];
        }

        return [];
    }

    /**
     * Get user's eBay products/listings
     */
    public function getProducts($limit = 50, $offset = 0)
    {
        try {
            $params = [
                'limit'  => $limit,
                'offset' => $offset
            ];

            $queryString = http_build_query($params);
            $endpoint    = "/sell/inventory/v1/inventory_item?$queryString";

            return $this->makeEbayRequest('get', $endpoint);
        } catch (Exception $e) {
            Log::error('Get eBay Products Error: '.$e->getMessage());

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
                'limit'  => $limit,
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
            $endpoint    = "/sell/inventory/v1/inventory_item?$queryString";

            return $this->makeEbayRequest('get', $endpoint);
        } catch (Exception $e) {
            Log::error('Search eBay Products Error: '.$e->getMessage());

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
            Log::error('Get eBay Product Error: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }


    /**
     * Create/Store product on eBay
     */
    public function storeProduct($productData)
    {
        try {
            $sku      = Arr::pull($productData, 'sku');
            $endpoint = "/sell/inventory/v1/inventory_item/$sku";

            return $this->makeEbayRequest('put', $endpoint, $productData);
        } catch (Exception $e) {
            Log::error('Store eBay Product Error: '.$e->getMessage());

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
            Log::error('Update eBay Product Error: '.$e->getMessage());

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
            Log::error('Update eBay Product Price and Quantity Error: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Store offer on eBay
     *
     * @throws \Exception
     */
    public function storeOffer($offerData)
    {
        $marketplaceId = Arr::get($this->getEbayConfig(), 'marketplace_id');
        $currency      = Arr::get($this->getEbayConfig(), 'currency');

        $data = [
            "sku"                 => Arr::get($offerData, 'sku'),
            "marketplaceId"       => $marketplaceId,
            "format"              => "FIXED_PRICE",
            "listingDescription"  => Arr::get($offerData, 'description'),
            "availableQuantity"   => Arr::get($offerData, 'quantity', 1),
            "pricingSummary"      => [
                "price" => [
                    "value"    => Arr::get($offerData, 'price', 0),
                    "currency" => $currency
                ]
            ],
            "listingPolicies"     => [
                "fulfillmentPolicyId" => $this->fulfillment_policy_id,
                "paymentPolicyId"     => $this->payment_policy_id,
                "returnPolicyId"      => $this->return_policy_id,
            ],
            "categoryId"          => Arr::get($offerData, 'category_id'),
            "merchantLocationKey" => $this->location_key,
        ];

        try {
            $endpoint = "/sell/inventory/v1/offer";

            return $this->makeEbayRequest('post', $endpoint, $data);
        } catch (Exception $e) {
            Log::error('Create eBay Product Offer: '.$e->getMessage());

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
            Log::error('Get eBay Listing Error: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    public function getListings($limit = 50, $offset = 0)
    {
        try {
            $endpoint = "/sell/inventory/v1/listing";

            return $this->makeEbayRequest('get', $endpoint, [], [
                'limit'  => $limit,
                'offset' => $offset
            ]);
        } catch (Exception $e) {
            Log::error('Get eBay Listings Error: '.$e->getMessage());

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
            Log::error('Get eBay Offers Error: '.$e->getMessage());

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
            Log::error('Get eBay Offer Error: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }


    /**
     * Update offer by offer ID
     *
     * @throws \Exception
     */
    public function updateOffer($offerId, array $offerData)
    {
        $currency = Arr::get($this->getEbayConfig(), 'currency');

        try {
            $data = [
                "format"              => "FIXED_PRICE",
                "listingDescription"  => Arr::get($offerData, 'description'),
                "availableQuantity"   => Arr::get($offerData, 'quantity', 1),
                "pricingSummary"      => [
                    "price" => [
                        "value"    => Arr::get($offerData, 'price', 0),
                        "currency" => $currency
                    ]
                ],
                "listingPolicies"     => [
                    "fulfillmentPolicyId" => $this->fulfillment_policy_id,
                    "paymentPolicyId"     => $this->payment_policy_id,
                    "returnPolicyId"      => $this->return_policy_id,
                ],
                "categoryId"          => Arr::get($offerData, 'category_id'),
                "merchantLocationKey" => $this->location_key,
            ];

            $endpoint = "/sell/inventory/v1/offer/$offerId";

            return $this->makeEbayRequest('put', $endpoint, $data);
        } catch (Exception $e) {
            Log::error('Get eBay Offer Error: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }


    /**
     * Update offer by offer ID
     */
    public function updateQuantityOffer($offerId, array $offerData)
    {
        try {
            $data = [
                "availableQuantity" => Arr::get($offerData, 'availableQuantity'),
            ];

            $endpoint = "/sell/inventory/v1/offer/$offerId";

            return $this->makeEbayRequest('put', $endpoint, $data);
        } catch (Exception $e) {
            Log::error('Get eBay Offer Error: '.$e->getMessage());

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
            Log::error('Delete eBay Product Error: '.$e->getMessage());

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
            Log::error('Delete eBay Offer Error: '.$e->getMessage());

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
                'limit'  => $limit,
                'offset' => $offset
            ];

            if ($orderIds) {
                $params['orderIds'] = is_array($orderIds) ? implode(',', $orderIds) : $orderIds;
            }

            if ($filter) {
                $params['filter'] = $filter;
            }


            $queryString = http_build_query($params);
            $endpoint    = "/sell/fulfillment/v1/order?$queryString";

            return $this->makeEbayRequest('get', $endpoint);
        } catch (Exception $e) {
            Log::error('Get eBay Orders Error: '.$e->getMessage());

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
            Log::error('Get eBay Order Error: '.$e->getMessage());

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
                'lineItems'           => $fulfillmentData['line_items'],
                'shippedDate'         => now()->toISOString(),
                'shippingCarrierCode' => $fulfillmentData['carrier_code'] ?? 'USPS',
                'trackingNumber'      => $fulfillmentData['tracking_number'] ?? null
            ];

            return $this->makeEbayRequest('post', $endpoint, $fulfillment);
        } catch (Exception $e) {
            $errMsg = 'Fulfill eBay Order Error: '.$e->getMessage();
            Log::error($errMsg);
            \Sentry::captureMessage($errMsg);

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
                            'email'    => $order['buyer']['buyerRegistrationAddress']['email'] ?? null,
                            'name'     => $order['buyer']['buyerRegistrationAddress']['fullName'] ?? null,
                            'address'  => $order['fulfillmentStartInstructions'][0]['shippingStep']['shipTo'] ?? null
                        ];
                    }
                }

                return array_unique($customers, SORT_REGULAR);
            }

            return $orders;
        } catch (Exception $e) {
            Log::error('Get eBay Customers Error: '.$e->getMessage());

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
            Log::error('Publish eBay Listing Error: '.$e->getMessage());

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
            Log::error('Get eBay Account Info Error: '.$e->getMessage());

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
            Log::error('Opt in Program Error: '.$e->getMessage());

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
            Log::error('Get Opt in Program Error: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Create a user's eBay fulfilment policy
     *
     * @throws \Exception
     */
    public function createFulfilmentPolicy($attributes)
    {
        $marketplaceId = Arr::get($this->getEbayConfig(), 'marketplace_id');
        $currency      = Arr::get($this->getEbayConfig(), 'currency');

        $default = $this->defaultCarrier()[$marketplaceId];

        $data = [
            "categoryTypes"   => [
                [
                    "name" => "ALL_EXCLUDING_MOTORS_VEHICLES"
                ]
            ],
            "marketplaceId"   => $marketplaceId,
            "name"            => "Shipping-".$this->customerSalesChannel?->slug,
            "handlingTime"    => [
                "unit"  => "DAY",
                "value" => Arr::get($attributes, 'max_dispatch_time', 1)
            ],
            "shippingOptions" => [
                [
                    "costType"         => "FLAT_RATE",
                    "optionType"       => "DOMESTIC",
                    "shippingServices" => [
                        [
                            "buyerResponsibleForShipping" => "false",
                            "freeShipping"                => "false",
                            "shippingCost"                => [
                                'currency' => $currency,
                                'value'    => Arr::get($attributes, 'price', 1)
                            ],
                            "shippingCarrierCode"         => Arr::get($attributes, 'carrier_code', $default['carrier_code']),
                            "shippingServiceCode"         => Arr::get($attributes, 'service_code', $default['service_code'])
                        ]
                    ]
                ]
            ]
        ];

        try {
            $endpoint = "/sell/account/v1/fulfillment_policy";

            return $this->makeEbayRequest('post', $endpoint, $data);
        } catch (Exception $e) {
            Log::error('Create Fulfilment Policy Error: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @throws \Exception
     */
    public function updateFulfilmentPolicy($fulfillmentPolicyId, $attributes)
    {
        $marketplaceId = Arr::get($this->getEbayConfig(), 'marketplace_id');
        $currency      = Arr::get($this->getEbayConfig(), 'currency');

        $defaults   = Arr::get($this->settings, 'shipping');
        $attributes = Arr::get($attributes, 'settings.shipping');

        $data = [
            "categoryTypes"   => [
                [
                    "name" => "ALL_EXCLUDING_MOTORS_VEHICLES"
                ]
            ],
            "marketplaceId"   => $marketplaceId,
            "name"            => "Shipping",
            "globalShipping"  => false,
            "handlingTime"    => [
                "unit"  => "DAY",
                "value" => Arr::get($attributes, 'max_dispatch_time', Arr::get($defaults, 'max_dispatch_time'))
            ],
            "shippingOptions" => [
                [
                    "costType"         => "FLAT_RATE",
                    "optionType"       => "DOMESTIC",
                    "shippingServices" => [
                        [
                            "buyerResponsibleForShipping" => "false",
                            "freeShipping"                => "false",
                            "shippingCost"                => [
                                'currency' => $currency,
                                'value'    => Arr::get($attributes, 'price', Arr::get($defaults, 'price'))
                            ],
                            "shippingCarrierCode"         => Arr::get($attributes, 'carrier_code', Arr::get($defaults, 'carrier_code')),
                            "shippingServiceCode"         => Arr::get($attributes, 'service_code', Arr::get($defaults, 'service_code'))
                        ]
                    ]
                ]
            ]
        ];

        try {
            $endpoint = "/sell/account/v1/fulfillment_policy/$fulfillmentPolicyId";

            return $this->makeEbayRequest('put', $endpoint, $data);
        } catch (Exception $e) {
            Log::error('Edit Fulfilment Policy Error: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get user's eBay Fulfillment Policies
     *
     * @throws \Exception
     */
    public function getFulfilmentPolicies()
    {
        $marketplaceId = Arr::get($this->getEbayConfig(), 'marketplace_id');

        try {
            $endpoint = "/sell/account/v1/fulfillment_policy";

            return $this->makeEbayRequest('get', $endpoint, [], [
                'marketplace_id' => $marketplaceId
            ]);
        } catch (Exception $e) {
            Log::error('Get Fulfilment Policy Error: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Create a user's eBay payment policy
     *
     * @throws \Exception
     */
    public function createPaymentPolicy()
    {
        $marketplaceId = Arr::get($this->getEbayConfig(), 'marketplace_id');

        $data = [
            "name"          => "Payment Policy-".$this->customerSalesChannel?->slug,
            "marketplaceId" => $marketplaceId,
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
            Log::error('Create Payment Policy Error: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get user's eBay Payment Policies
     *
     * @throws \Exception
     */
    public function getPaymentPolicies()
    {
        $marketplaceId = Arr::get($this->getEbayConfig(), 'marketplace_id');

        try {
            $endpoint = "/sell/account/v1/payment_policy";

            return $this->makeEbayRequest('get', $endpoint, [], [
                'marketplace_id' => $marketplaceId
            ]);
        } catch (Exception $e) {
            Log::error('Get Payment Policy Error: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Create a user's eBay return policy
     *
     * @throws \Exception
     */
    public function createReturnPolicy()
    {
        $marketplaceId = Arr::get($this->getEbayConfig(), 'marketplace_id');

        $data = [
            "name"                    => "Return Policy-".$this->customerSalesChannel?->slug,
            "marketplaceId"           => $marketplaceId,
            "refundMethod"            => "MONEY_BACK",
            "returnsAccepted"         => true,
            "returnShippingCostPayer" => "SELLER",
            "returnPeriod"            => [
                "value" => 30,
                "unit"  => "DAY"
            ]
        ];

        try {
            $endpoint = "/sell/account/v1/return_policy";

            return $this->makeEbayRequest('post', $endpoint, $data);
        } catch (Exception $e) {
            Log::error('Create Return Policy Error: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @throws \Exception
     */
    public function updateReturnPolicy($returnId, $returnData)
    {
        $marketplaceId = Arr::get($this->getEbayConfig(), 'marketplace_id');

        $defaults   = Arr::get($this->settings, 'return');
        $attributes = Arr::get($returnData, 'settings.return');

        $data = [
            "name"                    => "minimal return policy",
            "categoryTypes"           => [
                [
                    "name" => "ALL_EXCLUDING_MOTORS_VEHICLES"
                ]
            ],
            "marketplaceId"           => $marketplaceId,
            "description"             => Arr::get($attributes, 'description', Arr::get($defaults, 'description')),
            "returnsAccepted"         => Arr::get($attributes, 'accepted', Arr::get($defaults, 'accepted')),
            "returnShippingCostPayer" => Arr::get($attributes, 'payer', Arr::get($defaults, 'payer')),
            "returnPeriod"            => [
                "value" => Arr::get($attributes, 'within', Arr::get($defaults, 'within')),
                "unit"  => "DAY"
            ]
        ];

        try {
            $endpoint = "/sell/account/v1/return_policy/$returnId";

            return $this->makeEbayRequest('put', $endpoint, $data);
        } catch (Exception $e) {
            Log::error('Update Return Policy Error: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get user's eBay Return Policies
     *
     * @throws \Exception
     */
    public function getReturnPolicies()
    {
        $marketplaceId = Arr::get($this->getEbayConfig(), 'marketplace_id');

        try {
            $endpoint = "/sell/account/v1/return_policy";

            return $this->makeEbayRequest('get', $endpoint, [], [
                'marketplace_id' => $marketplaceId
            ]);
        } catch (Exception $e) {
            Log::error('Get Return Policy Error: '.$e->getMessage());

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
            Log::error('Get Inventory Location Error: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Create a user's eBay inventory location
     */
    public function createInventoryLocation($locationData)
    {
        $data = [
            "location"               => [
                "address" => [
                    "city"            => Arr::get($locationData, 'city'),
                    "stateOrProvince" => Arr::get($locationData, 'state'),
                    "country"         => Arr::get($locationData, 'country'),
                ]
            ],
            "name"                   => Arr::get($locationData, 'name', 'Default Location'),
            "merchantLocationStatus" => "ENABLED",
            "locationTypes"          => [
                "WAREHOUSE"
            ]
        ];

        try {
            $locationKey = Arr::get($locationData, 'locationKey');
            $endpoint    = "/sell/inventory/v1/location/$locationKey";

            return $this->makeEbayRequest('post', $endpoint, $data);
        } catch (Exception $e) {
            Log::error('Create Inventory Location Error: '.$e->getMessage());

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
            $endpoint       = "/commerce/taxonomy/v1/category_tree/3/get_category_suggestions";

            return $this->makeEbayRequest('get', $endpoint, [], [
                'q' => $encodedKeyword
            ]);
        } catch (Exception $e) {
            Log::error('Get Category Suggestions Error: '.$e->getMessage());

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
                'q'     => $keyword,
                'limit' => 10
            ]);
        } catch (Exception $e) {
            Log::error('Get Category Suggestions Error: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get user's eBay category suggestions
     */
    public function getPrivileges()
    {
        try {
            $endpoint = "/sell/account/v1/privilege";

            return $this->makeEbayRequest('get', $endpoint);
        } catch (Exception $e) {
            Log::error('Get Privilege Error: '.$e->getMessage());

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
            $url   = "https://apiz.ebay.com/commerce/identity/v1/user/";

            $response = Http::withHeaders([
                'Authorization'    => 'Bearer '.$token,
                'Content-Type'     => 'application/json',
                'Accept'           => 'application/json',
                'Content-Language' => 'en-GB'
            ])->withQueryParameters($queryParams)
                ->get($url, $data);

            if ($response->successful()) {
                return $response->json();
            }

            // If unauthorized, try to refresh the token once
            if ($response->status() === 401) {
                $token = Arr::get($this->refreshEbayToken(), 'access_token');

                if (!$token) {
                    return [];
                }

                $response = Http::withHeaders([
                    'Authorization'    => 'Bearer '.$token,
                    'Content-Type'     => 'application/json',
                    'Accept'           => 'application/json',
                    'Content-Language' => 'en-GB'
                ])->get($url, $data);

                if ($response->successful()) {
                    return $response->json();
                }
            }
        } catch (Exception $e) {
            Log::error('eBay API Request Error: '.$e->getMessage());
        }

        return null;
    }
}
