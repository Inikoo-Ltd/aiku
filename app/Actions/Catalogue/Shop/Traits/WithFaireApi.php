<?php

namespace App\Actions\Catalogue\Shop\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

trait WithFaireApi
{
    protected string $baseUrl = 'https://www.faire.com/external-api/v2/';
    protected array $defaultHeaders = [];


    protected function initializeApi($isFileDownload = false): void
    {
        $headers = [];
        if (!$isFileDownload) {
            $headers = [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json'
            ];
        }

        $this->defaultHeaders = [
            ...$headers,
            'X-FAIRE-ACCESS-TOKEN' => Arr::get($this->settings, 'faire.access_token')
        ];
    }


    protected function buildRequest(string $method, string $endpoint, array $params = [], array|null $data = [], $isFileDownload = false): array|string
    {
        $this->initializeApi($isFileDownload);

        $url = $this->baseUrl.trim($endpoint, '/');

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
            'error'   => $response->json()
        ];
    }

    /**
     * Get products with optional filtering
     *
     * @param  array  $params
     *
     * @return array
     */
    public function getFaireProducts(array $params = []): array
    {
        return $this->buildRequest('GET', 'products', $params);
    }


    public function getFaireRetailers(string $retailerId): array
    {
        return $this->buildRequest('GET', "retailers/public/$retailerId");
    }


    public function getFaireOrders(array $params = []): array
    {
        return $this->buildRequest('GET', 'orders', $params);
    }


    public function getFaireBrand(): array
    {
        $endpoint = 'brands/profile';

        return $this->buildRequest('GET', $endpoint);
    }

    public function getFaireProduct(string $productId): array
    {
        return $this->buildRequest('GET', "products/$productId");
    }

    public function getFaireOrder(string $orderId): array
    {
        return $this->buildRequest('GET', "orders/$orderId");
    }


    public function acceptFaireOrder(string $orderId, array $attributes = []): array
    {
        return $this->buildRequest('PUT', "orders/$orderId/processing", $attributes, null);
    }

    public function cancelFaireOrder(string $orderId, array $attributes = []): array
    {
        return $this->buildRequest('PUT', "orders/$orderId/cancel", $attributes);
    }

    public function updateShippingFaireOrder(string $orderId, array $attributes): array
    {
        return $this->buildRequest('PUT', "orders/$orderId/shipments", $attributes);
    }

    public function getPackingSlip(string $orderId): array|string
    {
        return $this->buildRequest(method: 'GET', endpoint: "orders/$orderId/packing-slip-pdf", isFileDownload: true);
    }


    public function updateInventoryQuantity(array $inventories): array
    {
        return $this->buildRequest('PATCH', "product-inventory/by-product-variant-ids", data: [
            'inventories' => $inventories
        ]);
    }
}
