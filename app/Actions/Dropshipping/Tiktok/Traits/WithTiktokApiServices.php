<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Oct 2023 17:06:28 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Traits;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

trait WithTiktokApiServices
{
    public string $version = '202309';

    public function generateSignature($path, $params, $appSecret, $body): string
    {
        ksort($params);

        $baseString = $path;

        foreach ($params as $key => $value) {
            $baseString .= $key . $value;
        }

        if (! blank($body)) {
            $jsonBody = json_encode($body);
            $baseString .=  $jsonBody;
        }

        $baseString = $appSecret.$baseString.$appSecret;

        return hash_hmac('sha256', $baseString, $appSecret);
    }

    public function restApi($path = null, $body = [], bool $requireShopCipher = true, array $headers = [], bool $requireSign = true, array $params = []): PendingRequest
    {
        $timestamp = now()->timestamp;
        $appKey = config('services.tiktok.client_id');
        $appSecret = config('services.tiktok.client_secret');

        $shopCipher = [];
        if ($requireShopCipher) {
            $shopCipher = [
                'shop_cipher' => Arr::get($this->data, 'authorized_shop.0.cipher')
            ];
        }

        $params = array_merge($params, [
            'app_key' => $appKey,
            'timestamp' => $timestamp,
            ...$shopCipher
        ]);

        if (Arr::get($headers, 'content-type') === 'multipart/form-data') {
            $http = Http::asMultipart();
            $body = [];
        } else {
            $http = Http::asJson();
        }

        $signature = $this->generateSignature($path, $params, $appSecret, $body);

        if ($requireSign) {
            $params = array_merge($params, ['sign' => $signature]);
        }

        return $http->withHeaders([
            'x-tts-access-token' => $this->access_token,
            ...Arr::except($headers, 'content-type')
        ])->baseUrl(config('services.tiktok.base_url'))
            ->withQueryParameters($params);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function makeApiRequest(string $method, string $path, array $productData = [], bool $requireShopCipher = true, array $headers = [], bool $requireSign = true, array $params = [])
    {
        try {
            $apiRequest = $this->restApi($path, $productData, $requireShopCipher, $headers, $requireSign, $params);

            $response = match (strtoupper($method)) {
                'POST' => $apiRequest->post($path, $productData),
                'GET' => $apiRequest->get($path),
                'PATCH' => $apiRequest->patch($path, $productData),
                'PUT' => $apiRequest->put($path, $productData),
                default => throw new \Exception("Unsupported HTTP method: $method"),
            };

            if (Arr::get($response->json(), 'message') !== "Success") {
                throw new \Exception(Arr::get($response->json(), 'message'));
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('API Request failed: ' . $e->getMessage());
            throw ValidationException::withMessages(['message' => $e->getMessage()]);
        }
    }

    public function getAuthorizedShop(): array
    {
        $path = '/authorization/'.$this->version.'/shops';

        return $this->makeApiRequest('GET', $path, [], false, [
            'content-type' => 'application/json'
        ]);
    }

    public function getWarehouses(): array
    {
        $path = '/logistics/'.$this->version.'/warehouses';

        return $this->makeApiRequest('GET', $path, [], true, [
            'content-type' => 'application/json'
        ]);
    }

    public function getShippingProviders(string $deliveryOptionId): array
    {
        $path = "/logistics/$this->version/delivery_options/$deliveryOptionId/shipping_providers";

        return $this->makeApiRequest('GET', $path, [], true, [
            'content-type' => 'application/json'
        ]);
    }

    public function getDeliveryOptions(): array
    {
        $path = "/logistics/$this->version/warehouses/$this->tiktok_warehouse_id/delivery_options";

        return $this->makeApiRequest('GET', $path, [], true, [
            'content-type' => 'application/json'
        ]);
    }

    public function updateWebhook(string $eventType, string $eventAddress): array
    {
        $path = '/event/'.$this->version.'/webhooks';

        return $this->makeApiRequest('PUT', $path, [
            'address' => $eventAddress,
            'event_type' => $eventType
        ], true, [
            'content-type' => 'application/json'
        ]);
    }

    public function uploadProductImageToTiktok(array $productData): array
    {
        $path = '/product/'.$this->version.'/images/upload';

        return $this->makeApiRequest('POST', $path, $productData, false, [
            'content-type' => 'multipart/form-data'
        ]);
    }

    public function uploadProductToTiktok(array $productData): array
    {
        $path = '/product/'.$this->version.'/products';

        return $this->makeApiRequest('POST', $path, $productData, true, [
            'content-type' => 'application/json'
        ]);
    }

    public function getOrders(array $params = [], array $body = []): array
    {
        $path = '/order/'.$this->version.'/orders/search';

        return $this->makeApiRequest('POST', $path, $body, true, [
            'content-type' => 'application/json'
        ], true, $params);
    }

    public function getOrder(string $orderId): array
    {
        $path = '/order/'.$this->version.'/orders';

        return $this->makeApiRequest('GET', $path, [], true, [
            'content-type' => 'application/json'
        ], true, [
            'ids' => $orderId
        ]);
    }

    public function getProducts(array $productData, array $params): array
    {
        $path = '/product/'.$this->version.'/products/search';

        return $this->makeApiRequest('POST', $path, $productData, true, [
            'content-type' => 'application/json'
        ], true, $params);
    }

    public function getProduct(string $productId): array
    {
        $path = '/product/'.$this->version.'/products/' . $productId;

        return $this->makeApiRequest('GET', $path, [], true, [
            'content-type' => 'application/json'
        ]);
    }

    public function updateShippingInfo(string $orderId, array $shippingData): array
    {
        $path = "/fulfillment/$this->version/orders/$orderId/shipping_info/update";

        return $this->makeApiRequest('POST', $path, $shippingData, true, [
            'content-type' => 'application/json'
        ]);
    }
}
