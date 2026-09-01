<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Traits;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

trait WithWixApiServices
{
    use WithWixOAuth;

    public function restApi(array $params = []): PendingRequest
    {
        if (!$this->access_token_expire_in || $this->access_token_expire_in < now()->timestamp) {
            $this->refreshAccessToken($this->refresh_token);
        }

        $http = Http::withHeaders([
            'Authorization' => $this->access_token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])->baseUrl(config('services.wix.api_url'));

        if (!empty($params)) {
            $http = $http->withQueryParameters($params);
        }

        return $http;
    }

    public function makeApiRequest(string $method, string $path, array $body = [], array $params = []): array
    {
        try {
            $api = $this->restApi($params);

            $response = match (strtoupper($method)) {
                'GET'    => $api->get($path),
                'POST'   => $api->post($path, $body),
                'PATCH'  => $api->patch($path, $body),
                'PUT'    => $api->put($path, $body),
                'DELETE' => $api->delete($path),
                default  => throw new \Exception("Unsupported HTTP method: $method"),
            };

            if ($response->failed()) {
                $json = $response->json();

                return [
                    'message' => Arr::get($json, 'message')
                        ?? Arr::get($json, 'details.applicationError.description')
                        ?? Arr::get($json, 'error')
                        ?? 'Unknown Wix API error',
                ];
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            return ['message' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------------------------
    // Instance & site
    // -------------------------------------------------------------------------

    public function getAppInstance(): array
    {
        return $this->makeApiRequest('GET', '/apps/v1/instance');
    }

    public function getSiteProperties(): array
    {
        return $this->makeApiRequest('GET', '/site-properties/v4/properties');
    }

    /**
     * The connectivity probe used by the channel health checks.
     */
    public function getUserInfo(): array
    {
        $instance = $this->getAppInstance();

        if (Arr::get($instance, 'message')) {
            throw new \Exception(Arr::get($instance, 'message'));
        }

        return $instance;
    }

    // -------------------------------------------------------------------------
    // Products
    // -------------------------------------------------------------------------

    public function queryProducts(array $query = []): array
    {
        return $this->makeApiRequest('POST', '/stores/v1/products/query', [
            'query' => $query,
        ]);
    }

    public function getProduct(string $productId): array
    {
        return $this->makeApiRequest('GET', "/stores/v1/products/$productId");
    }

    public function searchProductsBySku(string $sku): array
    {
        $response = $this->queryProducts([
            'filter' => json_encode(['sku' => ['$eq' => $sku]]),
            'paging' => ['limit' => 10, 'offset' => 0],
        ]);

        return Arr::get($response, 'products', []);
    }

    public function createProduct(array $productData): array
    {
        return $this->makeApiRequest('POST', '/stores/v1/products', [
            'product' => $productData,
        ]);
    }

    public function updateProduct(string $productId, array $productData): array
    {
        return $this->makeApiRequest('PATCH', "/stores/v1/products/$productId", [
            'product' => $productData,
        ]);
    }

    public function deleteProduct(string $productId): array
    {
        return $this->makeApiRequest('DELETE', "/stores/v1/products/$productId");
    }

    public function addProductMedia(string $productId, array $media): array
    {
        return $this->makeApiRequest('POST', "/stores/v1/products/$productId/media", [
            'media' => $media,
        ]);
    }

    // -------------------------------------------------------------------------
    // Inventory
    // -------------------------------------------------------------------------

    public function queryInventoryItems(array $query = []): array
    {
        return $this->makeApiRequest('POST', '/stores/v2/inventoryItems/query', [
            'query' => $query,
        ]);
    }

    public function getInventoryItemIdForProduct(string $productId): ?string
    {
        $response = $this->queryInventoryItems([
            'filter' => json_encode(['productId' => ['$eq' => $productId]]),
            'paging' => ['limit' => 1, 'offset' => 0],
        ]);

        return Arr::get($response, 'inventoryItems.0.id');
    }

    public function updateInventoryVariants(string $inventoryItemId, array $variants): array
    {
        return $this->makeApiRequest('PATCH', "/stores/v2/inventoryItems/$inventoryItemId", [
            'inventoryItem' => [
                'trackQuantity' => true,
                'variants'      => $variants,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Orders
    // -------------------------------------------------------------------------

    public function searchOrders(array $search = []): array
    {
        return $this->makeApiRequest('POST', '/ecom/v1/orders/search', [
            'search' => $search,
        ]);
    }

    public function getOrder(string $orderId): array
    {
        return $this->makeApiRequest('GET', "/ecom/v1/orders/$orderId");
    }

    public function createOrderFulfillment(string $orderId, array $fulfillment): array
    {
        return $this->makeApiRequest('POST', "/ecom/v1/fulfillments/orders/$orderId/fulfillments", [
            'fulfillment' => $fulfillment,
        ]);
    }

    public function cancelOrder(string $orderId, bool $restockItems = false): array
    {
        return $this->makeApiRequest('POST', "/ecom/v1/orders/$orderId/cancel", [
            'restockAllItems' => $restockItems,
        ]);
    }
}
