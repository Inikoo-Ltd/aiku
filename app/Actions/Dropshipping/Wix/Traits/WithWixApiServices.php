<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Traits;

use App\Actions\Dropshipping\Wix\Catalog\WixCatalog;
use App\Actions\Dropshipping\Wix\Catalog\WixCatalogV1;
use App\Actions\Dropshipping\Wix\Catalog\WixCatalogV3;
use App\Enums\Dropshipping\WixCatalogVersionEnum;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait WithWixApiServices
{
    use WithWixOAuth;

    public function restApi(array $params = []): PendingRequest
    {
        if (!$this->hasFreshAccessToken()) {
            $this->renewAccessToken();
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

                $message = Arr::get($json, 'message')
                    ?? Arr::get($json, 'details.applicationError.description')
                    ?? Arr::get($json, 'error')
                    ?? 'Unknown Wix API error';

                return [
                    'message'          => $this->translateWixError($message).$this->violationSuffix($json),
                    'field_violations' => $this->fieldViolations($json),
                ];
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            return ['message' => $e->getMessage()];
        }
    }

    public function fieldViolations(mixed $json): array
    {
        if (!is_array($json)) {
            return [];
        }

        $violations = Arr::get($json, 'details.validationError.fieldViolations')
            ?? Arr::get($json, 'details.applicationError.data.fieldViolations')
            ?? [];

        return collect($violations)
            ->map(fn ($violation) => [
                'field'       => Arr::get($violation, 'field'),
                'description' => Arr::get($violation, 'description'),
            ])
            ->all();
    }

    private function violationSuffix(mixed $json): string
    {
        $violations = collect($this->fieldViolations($json))
            ->map(fn ($violation) => trim($violation['field'].' '.$violation['description']))
            ->filter()
            ->join('; ');

        return $violations ? ' ('.$violations.')' : '';
    }

    // -------------------------------------------------------------------------
    // Catalogue version
    // -------------------------------------------------------------------------

    /**
     * Which Wix Stores catalogue the site answers. Permanent per site once set, so it is read
     * once and remembered rather than asked on every call.
     *
     * @see https://dev.wix.com/docs/rest/business-solutions/stores/catalog-versioning/introduction
     */
    public function getCatalogVersion(bool $fresh = false): WixCatalogVersionEnum
    {
        $cached = Arr::get($this->data, 'catalog_version');

        if (!$fresh && $cached && $cached !== WixCatalogVersionEnum::STORES_NOT_INSTALLED->value) {
            return WixCatalogVersionEnum::from($cached);
        }

        $response = $this->makeApiRequest('GET', '/stores/v3/provision/version');

        $version = WixCatalogVersionEnum::tryFrom((string) Arr::get($response, 'catalogVersion'))
            ?? WixCatalogVersionEnum::STORES_NOT_INSTALLED;

        if ($cached !== $version->value) {
            $data = $this->data ?? [];
            data_set($data, 'catalog_version', $version->value);
            $this->forceFill(['data' => $data])->saveQuietly();
        }

        return $version;
    }

    public function catalog(): WixCatalog
    {
        return match ($this->getCatalogVersion()) {
            WixCatalogVersionEnum::V3 => new WixCatalogV3($this),
            default => new WixCatalogV1($this),
        };
    }

    /**
     * Whether the site can serve the catalogue this integration depends on.
     */
    public function hasWixStores(): bool
    {
        return $this->getCatalogVersion(true) !== WixCatalogVersionEnum::STORES_NOT_INSTALLED;
    }

    /**
     * Wix answers a call to a business solution the site does not have with an internal
     * "TPA <app id> is not installed" string, which says nothing to the seller reading it.
     */
    public function translateWixError(string $message): string
    {
        if (preg_match('/TPA .* is not installed/i', $message)) {
            return __('Wix Stores is not installed on this site. Add the Wix Stores app to the site, then reconnect the channel.');
        }

        return $message;
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
    // Catalogue
    //
    // Products and inventory are reached through catalog(), never from here: the endpoints and
    // payloads differ between catalogue V1 and V3, and only the drivers know which is which.
    // -------------------------------------------------------------------------

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
