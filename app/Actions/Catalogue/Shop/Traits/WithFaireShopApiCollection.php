<?php

namespace App\Actions\Catalogue\Shop\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

trait WithFaireShopApiCollection
{
    protected string $baseUrl = 'https://www.faire.com/external-api/v2/';
    protected array $defaultHeaders = [];

    /**
     * Initialize the API configuration
     *
     * @param string $token
     * @return void
     */
    protected function initializeApi($isFileDownload = false): void
    {
        $headers = [];
        if (! $isFileDownload) {
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ];
        }

        $this->defaultHeaders = [
            ...$headers,
            'X-FAIRE-ACCESS-TOKEN' => Arr::get($this->settings, 'faire.access_token')
        ];
    }

    /**
     * Build API request configuration
     *
     * @param string $method
     * @param string $endpoint
     * @param array $params
     * @return array
     */
    protected function buildRequest(string $method, string $endpoint, array $params = [], $data = [], $isFileDownload = false): array|string
    {
        $this->initializeApi($isFileDownload);

        $url = $this->baseUrl . trim($endpoint, '/');

        $response = Http::withHeaders($this->defaultHeaders)
            ->withQueryParameters($params)
            ->$method(
                $url,
                $data
            );

        if ($response->successful()) {
            if ($isFileDownload) {
                return $response->body();
            }

            return $response->json();
        }

        return [
            'success' => false,
            'error' => $response->json()
        ];
    }

    /**
     * Get products with optional filtering
     *
     * @param array $params
     * @return array
     */
    public function getFaireProducts(array $params = []): array
    {
        return $this->buildRequest('GET', 'products', $params);
    }

    /**
     * Get customers with optional filtering
     *
     * @param array $params
     * @return array
     */
    public function getFaireRetailers(string $retailerId): array
    {
        return $this->buildRequest('GET', "retailers/public/$retailerId");
    }

    /**
     * Get orders with optional filtering
     *
     * @param array $params
     * @return array
     */
    public function getFaireOrders(array $params = []): array
    {
        return $this->buildRequest('GET', 'orders', $params);
    }

    /**
     * Get brand details
     *
     * @param string|null $brandId
     * @return array
     */
    public function getFaireBrand(): array
    {
        $endpoint = 'brands/profile';

        return $this->buildRequest('GET', $endpoint);
    }

    /**
     * Get a specific product by ID
     *
     * @param string $productId
     * @return array
     */
    public function getFaireProduct(string $productId): array
    {
        return $this->buildRequest('GET', "products/{$productId}");
    }

    /**
     * Get a specific order by ID
     *
     * @param string $orderId
     * @return array
     */
    public function getFaireOrder(string $orderId): array
    {
        return $this->buildRequest('GET', "orders/{$orderId}");
    }

    /**
     * Update a specific order by ID
     *
     * @param string $orderId
     * @return array
     */
    public function acceptFaireOrder(string $orderId, array $attributes = []): array
    {
        return $this->buildRequest('PUT', "orders/{$orderId}/processing", $attributes);
    }

    /**
     * Update a specific order by ID
     *
     * @param string $orderId
     * @return array
     */
    public function cancelFaireOrder(string $orderId, array $attributes = []): array
    {
        return $this->buildRequest('PUT', "orders/{$orderId}/cancel", $attributes);
    }

    /**
     * Update shipping to a specific order by ID
     *
     * @param string $orderId
     * @return array
     */
    public function updateShippingFaireOrder(string $orderId, array $attributes): array
    {
        return $this->buildRequest('PUT', "orders/{$orderId}/shipments", $attributes);
    }

    /**
     * Get packing PDF specific order by ID
     *
     * @param string $orderId
     * @return array
     */
    public function getPackingSlip(string $orderId): array|string
    {
        return $this->buildRequest(method: 'GET', endpoint: "orders/{$orderId}/packing-slip-pdf", isFileDownload: true);
    }

    /**
     * Update Inventory Quantity
     *
     * @param string $orderId
     * @return array
     */
    public function updateInventoryQuantity(array $inventories): array
    {
        return $this->buildRequest('PATCH', "product-inventory/by-product-variant-ids", data: [
            'inventories' => $inventories
        ]);
    }
}
