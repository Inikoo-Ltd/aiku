<?php

/*
 * author Louis Perez
 * created on 10-04-2026-10h-32m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\Shop\Traits;

use App\Models\Catalogue\Product;
use Exception;
use Illuminate\Support\Facades\Http;

trait WithReviewIOApi
{
    protected string $baseReviewIoUrl = 'https://api.reviews.io';
    protected array $defaultReviewIoHeaders = [
            'accept'        => 'application/json',
            'content-type'  => 'application/json',
        ];
    protected array $queryReviewIoParams = [];

    public function initializeReviewIoApi(): void
    {
        if (!data_get($this->settings, 'reviews.enabled', false) || data_get($this->settings, 'reviews.provider', null) !== 'reviews.io') {
            throw new Exception('Unable to initialize. Invalid Review provider selected.', 422);
        }

        $reviewSetting = data_get($this->settings, 'reviews.data');

        $store = data_get($reviewSetting, 'store');
        $apikey = data_get($reviewSetting, 'apikey');
        $url = data_get($reviewSetting, 'url');

        if (!$store || !$apikey) {
            throw new \Exception('Missing required Reviews.io credentials (store or API key).', 422);
        }

        $this->baseReviewIoUrl = $url;

        $this->queryReviewIoParams = [
            'store'  => $store,
            'apikey' => $apikey,
        ];
    }

    public function buildReviewIoRequest(string $method = 'GET', string $endpoint, array $params = [], array|null $data = []): array|string
    {
        $this->initializeReviewIoApi();

        $url = $this->baseReviewIoUrl.rtrim($endpoint, '/');

        if ($params) {
            $this->queryReviewIoParams = array_merge($this->queryReviewIoParams, $params);
        }

        $response = Http::timeout(60)
            ->withHeaders($this->defaultReviewIoHeaders)
            ->withQueryParameters($this->queryReviewIoParams)
            ->$method(
                $url,
                $data
            );

        if ($response->successful()) {
            // if ( ?? ) {
            //     return $response->body();
            // }

            return $response->json();
        }

        return [
            'success' => false,
            'error'   => $response->json()
        ];
    }

    public function retrieveProductRatings(Product $product): array|string
    {
        unset($this->queryReviewIoParams['apikey']);

        return $this->buildReviewIoRequest('GET', '/product/rating-batch', [
            'sku'   =>  $product->code
        ]);
    }

    public function retrieveReviewsAndQuestions(Product|null $product = null, String|null $type = null): array|string
    {
        $additionalParams = [];

        if ($product) {
            data_set($additionalParams, 'sku', $product->code);
        }

        if (in_array($type, ['store_review', 'product_review', 'store_third_party_review', 'questions'])) {
            data_set($additionalParams, 'type', $type);
        }

        return $this->buildReviewIoRequest('GET', '/reviews', $additionalParams);
    }

    public function retrieveStoreReviewStatistics(): array|string
    {
        unset($this->queryReviewIoParams['apikey']);

        return $this->buildReviewIoRequest('GET', '/stats/all');
    }

}
