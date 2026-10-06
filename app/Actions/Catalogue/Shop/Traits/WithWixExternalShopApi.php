<?php

namespace App\Actions\Catalogue\Shop\Traits;

use App\Models\Dropshipping\WixUser;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

trait WithWixExternalShopApi
{
    public const string WIX_V1_DEFAULT_VARIANT_ID = '00000000-0000-0000-0000-000000000000';

    public const string WIX_CATALOG_V1 = 'V1_CATALOG';

    public const string WIX_CATALOG_V3 = 'V3_CATALOG';

    protected int $wixAccessTokenTtl = 14400;

    protected int $wixAccessTokenSkew = 300;

    protected int $wixPageSize = 100;

    protected int $wixMaxPages = 100;

    public function getWixInstallUrl(string $postInstallationUrl): string
    {
        $base = config('services.wix.install_url') ?: 'https://www.wix.com/app-installer';

        [$path, $query] = array_pad(explode('?', $base, 2), 2, '');
        parse_str($query, $params);

        $params['appId']               ??= config('services.wix.app_id');
        $params['shareUrlId']          = '196d8dfc-a856-4a10-80cf-cc39f62de4ab';
        $params['postInstallationUrl'] = $postInstallationUrl;

        return $path.'?'.http_build_query(array_filter($params));
    }

    public function verifyWixSignedInstance(?string $signedInstance): ?array
    {
        $appSecret = config('services.wix.app_secret');

        if (blank($signedInstance) || !str_contains($signedInstance, '.') || blank($appSecret)) {
            return null;
        }

        [$signature, $payload] = explode('.', $signedInstance, 2);

        $expected = hash_hmac('sha256', $payload, $appSecret, true);

        if (!hash_equals($expected, $this->wixBase64UrlDecode($signature))) {
            return null;
        }

        $decoded = json_decode($this->wixBase64UrlDecode($payload), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function wixBase64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'));
    }

    /**
     * @return array{access_token?: string, expires_in?: int, message?: string}
     */
    public function createWixAccessToken(string $instanceId): array
    {
        try {
            $response = Http::acceptJson()->post(config('services.wix.api_url').'/oauth2/token', [
                'grant_type'    => 'client_credentials',
                'client_id'     => config('services.wix.app_id'),
                'client_secret' => config('services.wix.app_secret'),
                'instance_id'   => $instanceId,
            ]);

            if ($response->failed()) {
                return [
                    'message' => Arr::get($response->json(), 'error_description')
                        ?? Arr::get($response->json(), 'message')
                        ?? 'Wix access token request failed',
                ];
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            return ['message' => $e->getMessage()];
        }
    }

    public function getWixAccessTokenExpiry(array $tokenData): int
    {
        $ttl = (int) Arr::get($tokenData, 'expires_in', $this->wixAccessTokenTtl);

        return now()->addSeconds(max($ttl - $this->wixAccessTokenSkew, 60))->timestamp;
    }

    public function getWixAccessToken(WixUser $wixUser): ?string
    {
        if ($wixUser->access_token && $wixUser->access_token_expire_in > now()->timestamp) {
            return $wixUser->access_token;
        }

        $tokenData = $this->createWixAccessToken($wixUser->wix_instance_id);

        if (!$accessToken = Arr::get($tokenData, 'access_token')) {
            return null;
        }

        $wixUser->forceFill([
            'access_token'           => $accessToken,
            'access_token_expire_in' => $this->getWixAccessTokenExpiry($tokenData),
        ])->saveQuietly();

        return $accessToken;
    }

    protected function wixHttp(WixUser $wixUser, array $params = []): PendingRequest
    {
        $http = Http::withHeaders([
            'Authorization' => (string) $this->getWixAccessToken($wixUser),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])->baseUrl(config('services.wix.api_url'));

        return $params ? $http->withQueryParameters($params) : $http;
    }

    public function wixRequest(WixUser $wixUser, string $method, string $path, array $body = [], array $params = []): array
    {
        try {
            $http = $this->wixHttp($wixUser, $params);

            $response = match (strtoupper($method)) {
                'GET'   => $http->get($path),
                'POST'  => $http->post($path, $body),
                'PATCH' => $http->patch($path, $body),
                default => throw new \Exception("Unsupported HTTP method: $method"),
            };

            if ($response->failed()) {
                $json = $response->json();

                return [
                    'message' => Arr::get($json, 'message')
                        ?: Arr::get($json, 'details.applicationError.description')
                            ?: __('Unknown Wix API error (HTTP :status)', ['status' => $response->status()]),
                ];
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            return ['message' => $e->getMessage()];
        }
    }

    public function getWixAppInstance(WixUser $wixUser): array
    {
        return $this->wixRequest($wixUser, 'GET', '/apps/v1/instance');
    }

    public function getWixSiteProperties(WixUser $wixUser): array
    {
        return $this->wixRequest($wixUser, 'GET', '/site-properties/v4/properties');
    }

    public function getWixCatalogVersion(WixUser $wixUser): ?string
    {
        $cached = Arr::get($wixUser->data, 'external_shop_catalog_version');

        if (in_array($cached, [self::WIX_CATALOG_V1, self::WIX_CATALOG_V3])) {
            return $cached;
        }

        $version = Arr::get($this->wixRequest($wixUser, 'GET', '/stores/v3/provision/version'), 'catalogVersion');

        if (!in_array($version, [self::WIX_CATALOG_V1, self::WIX_CATALOG_V3])) {
            return null;
        }

        $data = $wixUser->data ?? [];
        data_set($data, 'external_shop_catalog_version', $version);
        $wixUser->forceFill(['data' => $data])->saveQuietly();

        return $version;
    }

    /**
     * Every sellable variant of the site, flattened so V1 and V3 catalogues read the same.
     *
     * @return array{variants: array<int, array{product_id: string, variant_id: string, sku: ?string, name: string, description: ?string, price: float, image: ?string, visible: bool}>, complete: bool}
     */
    public function getAllWixVariants(WixUser $wixUser): array
    {
        return match ($this->getWixCatalogVersion($wixUser)) {
            self::WIX_CATALOG_V3 => $this->getAllWixV3Variants($wixUser),
            self::WIX_CATALOG_V1 => $this->getAllWixV1Variants($wixUser),
            default => ['variants' => [], 'complete' => false],
        };
    }

    protected function getAllWixV1Variants(WixUser $wixUser): array
    {
        $variants = [];

        for ($page = 0; $page < $this->wixMaxPages; $page++) {
            $response = $this->wixRequest($wixUser, 'POST', '/stores/v1/products/query', [
                'query'           => ['paging' => ['limit' => $this->wixPageSize, 'offset' => $page * $this->wixPageSize]],
                'includeVariants' => true,
            ]);

            $products = Arr::get($response, 'products');

            if (!is_array($products)) {
                return ['variants' => $variants, 'complete' => false];
            }

            foreach ($products as $product) {
                $productVariants = Arr::get($product, 'variants') ?: [[
                    'id'      => self::WIX_V1_DEFAULT_VARIANT_ID,
                    'choices' => [],
                    'variant' => [
                        'sku'       => Arr::get($product, 'sku'),
                        'priceData' => Arr::get($product, 'priceData'),
                        'visible'   => true,
                    ],
                ]];

                foreach ($productVariants as $variant) {
                    $variants[] = [
                        'product_id'  => (string) Arr::get($product, 'id'),
                        'variant_id'  => (string) Arr::get($variant, 'id'),
                        'sku'         => Arr::get($variant, 'variant.sku') ?: Arr::get($product, 'sku'),
                        'name'        => $this->getWixVariantName(Arr::get($product, 'name', ''), array_values(array_filter((array) Arr::get($variant, 'choices', [])))),
                        'description' => strip_tags((string) Arr::get($product, 'description', '')) ?: null,
                        'price'       => (float) (Arr::get($variant, 'variant.priceData.price') ?? Arr::get($product, 'priceData.price', 0)),
                        'image'       => Arr::get($product, 'media.mainMedia.image.url'),
                        'visible'     => (bool) Arr::get($product, 'visible', true) && (bool) Arr::get($variant, 'variant.visible', true),
                    ];
                }
            }

            if (count($products) < $this->wixPageSize) {
                return ['variants' => $variants, 'complete' => true];
            }
        }

        return ['variants' => $variants, 'complete' => false];
    }

    protected function getAllWixV3Variants(WixUser $wixUser): array
    {
        $variants = [];
        $cursor   = null;

        for ($page = 0; $page < $this->wixMaxPages; $page++) {
            $cursorPaging = ['limit' => $this->wixPageSize];

            if ($cursor) {
                $cursorPaging['cursor'] = $cursor;
            }

            $response = $this->wixRequest($wixUser, 'POST', '/stores/v3/products/search', [
                'search' => ['cursorPaging' => $cursorPaging],
                'fields' => ['PLAIN_DESCRIPTION'],
            ]);

            $products = Arr::get($response, 'products');

            if (!is_array($products)) {
                return ['variants' => $variants, 'complete' => false];
            }

            foreach ($products as $product) {
                if (!Arr::has($product, 'variantsInfo.variants')) {
                    $product = Arr::get(
                        $this->wixRequest($wixUser, 'GET', '/stores/v3/products/'.Arr::get($product, 'id'), params: ['fields' => 'PLAIN_DESCRIPTION']),
                        'product'
                    ) ?? $product;
                }

                foreach (Arr::get($product, 'variantsInfo.variants', []) as $variant) {
                    $choices = collect(Arr::get($variant, 'choices', []))
                        ->map(fn ($choice) => Arr::get($choice, 'optionChoiceNames.choiceName'))
                        ->filter()
                        ->values()
                        ->all();

                    $variants[] = [
                        'product_id'  => (string) Arr::get($product, 'id'),
                        'variant_id'  => (string) Arr::get($variant, 'id'),
                        'sku'         => Arr::get($variant, 'sku'),
                        'name'        => $this->getWixVariantName(Arr::get($product, 'name', ''), $choices),
                        'description' => Arr::get($product, 'plainDescription') ?: null,
                        'price'       => (float) Arr::get($variant, 'price.actualPrice.amount', 0),
                        'image'       => Arr::get($product, 'media.main.image.url'),
                        'visible'     => (bool) Arr::get($product, 'visible', true) && (bool) Arr::get($variant, 'visible', true),
                    ];
                }
            }

            $cursor = Arr::get($response, 'pagingMetadata.cursors.next');

            if (!$cursor || count($products) < $this->wixPageSize) {
                return ['variants' => $variants, 'complete' => true];
            }
        }

        return ['variants' => $variants, 'complete' => false];
    }

    protected function getWixVariantName(string $productName, array $choices): string
    {
        return $choices ? $productName.' - '.implode(' / ', $choices) : $productName;
    }

    /**
     * @param array<string, int> $variantQuantities quantity keyed by Wix variant id
     */
    public function setWixProductInventory(WixUser $wixUser, string $productId, array $variantQuantities): array
    {
        return match ($this->getWixCatalogVersion($wixUser)) {
            self::WIX_CATALOG_V3 => $this->setWixV3ProductInventory($wixUser, $productId, $variantQuantities),
            self::WIX_CATALOG_V1 => $this->setWixV1ProductInventory($wixUser, $productId, $variantQuantities),
            default => ['message' => __('Wix Stores is not installed on this site.')],
        };
    }

    /**
     * @param array<string, int> $variantQuantities
     */
    protected function setWixV1ProductInventory(WixUser $wixUser, string $productId, array $variantQuantities): array
    {
        $inventoryItemId = Arr::get($this->wixRequest($wixUser, 'POST', '/stores/v2/inventoryItems/query', [
            'query' => [
                'filter' => json_encode(['productId' => ['$eq' => $productId]]),
                'paging' => ['limit' => 1, 'offset' => 0],
            ],
        ]), 'inventoryItems.0.id');

        if (!$inventoryItemId) {
            return ['message' => 'Wix inventory item not found for product '.$productId];
        }

        return $this->wixRequest($wixUser, 'PATCH', "/stores/v2/inventoryItems/$inventoryItemId", [
            'inventoryItem' => [
                'trackQuantity' => true,
                'variants'      => collect($variantQuantities)
                    ->map(fn (int $quantity, string $variantId) => [
                        'variantId' => $variantId,
                        'inStock'   => $quantity > 0,
                        'quantity'  => $quantity,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    /**
     * @param array<string, int> $variantQuantities
     */
    protected function setWixV3ProductInventory(WixUser $wixUser, string $productId, array $variantQuantities): array
    {
        $errors = [];

        foreach ($variantQuantities as $variantId => $quantity) {
            $result = $this->setWixV3VariantInventory($wixUser, $productId, (string) $variantId, $quantity);

            if (Arr::has($result, 'message')) {
                $errors[] = Arr::get($result, 'message');
            }
        }

        return $errors ? ['message' => implode('; ', $errors)] : [];
    }

    protected function setWixV3VariantInventory(WixUser $wixUser, string $productId, string $variantId, int $quantity): array
    {
        $inventoryItem = Arr::get($this->wixRequest($wixUser, 'POST', '/stores/v3/inventory-items/search', [
            'search' => [
                'filter'       => [
                    'productId' => ['$eq' => $productId],
                    'variantId' => ['$eq' => $variantId],
                ],
                'cursorPaging' => ['limit' => 1],
            ],
        ]), 'inventoryItems.0');

        if (!$inventoryItemId = Arr::get($inventoryItem, 'id')) {
            return ['message' => 'Wix inventory item not found for variant '.$variantId];
        }

        return $this->wixRequest($wixUser, 'PATCH', "/stores/v3/inventory-items/$inventoryItemId", [
            'inventoryItem' => [
                'id'       => $inventoryItemId,
                'revision' => Arr::get($inventoryItem, 'revision'),
                'quantity' => $quantity,
            ],
            'reason'        => 'MANUAL',
        ]);
    }

    /**
     * @return array{orders: array<int, array<string, mixed>>, complete: bool}
     */
    public function getAllWixOrders(WixUser $wixUser, array $filter): array
    {
        $orders = [];
        $cursor = null;

        for ($page = 0; $page < $this->wixMaxPages; $page++) {
            $cursorPaging = ['limit' => $this->wixPageSize];

            if ($cursor) {
                $cursorPaging['cursor'] = $cursor;
            }

            $response = $this->wixRequest($wixUser, 'POST', '/ecom/v1/orders/search', [
                'search' => [
                    'filter'       => $filter,
                    'cursorPaging' => $cursorPaging,
                ],
            ]);

            $pageOrders = Arr::get($response, 'orders');

            if (!is_array($pageOrders)) {
                return ['orders' => $orders, 'complete' => false];
            }

            array_push($orders, ...$pageOrders);

            $cursor = Arr::get($response, 'metadata.cursors.next') ?: Arr::get($response, 'pagingMetadata.cursors.next');

            if (!$cursor || count($pageOrders) < $this->wixPageSize) {
                return ['orders' => $orders, 'complete' => true];
            }
        }

        return ['orders' => $orders, 'complete' => false];
    }

    public function createWixOrderFulfillment(WixUser $wixUser, string $orderId, array $fulfillment): array
    {
        return $this->wixRequest($wixUser, 'POST', "/ecom/v1/fulfillments/orders/$orderId/create-fulfillment", [
            'fulfillment' => $fulfillment,
        ]);
    }
}
