<?php

namespace App\Actions\Catalogue\Shop\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

trait WithOrderchampApi
{
    public function hasOrderchamp(): bool
    {
        return (bool)Arr::get($this->settings, 'orderchamp.access_token');
    }

    /**
     * Orderchamp has one GraphQL endpoint for everything. A failed request or a GraphQL error throws, so a caller
     * never mistakes a refused query for an empty answer.
     */
    public function orderchampQuery(string $query, array $variables = []): array
    {
        $response = Http::timeout(60)
            ->withToken((string)Arr::get($this->settings, 'orderchamp.access_token'))
            ->acceptJson()
            ->post('https://api.orderchamp.com/v1/graphql', array_filter(['query' => $query, 'variables' => $variables]));

        $errors = $response->json('errors');
        if (!$response->successful() || $errors) {
            throw new RuntimeException('Orderchamp API '.$response->status().': '.mb_substr(json_encode($errors ?: $response->body()), 0, 500));
        }

        return (array)$response->json('data');
    }
}
