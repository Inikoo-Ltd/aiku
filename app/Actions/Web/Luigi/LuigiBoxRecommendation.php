<?php

/*
 * author Louis Perez
 * created on 23-01-2026-08h-54m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\Luigi;

use App\Actions\IrisAction;
use App\Services\QueryBuilder;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use App\Http\Resources\Catalogue\IrisLuigiBoxRecommendationResource;
use App\Models\Catalogue\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class LuigiBoxRecommendation extends IrisAction
{

    public function handle(ActionRequest $request): LengthAwarePaginator|array
    {
        $customer = $request->user();
        $size = 25;
        $userId = $customer?->customer_id
            ? (string) $customer->customer_id
            : (string) ($request->cookie('_lb') ?? 'guest');

        $website   = $request->input('website');
        $trackerId = Arr::get($website->settings, 'luigisbox.tracker_id');


        $luigi_identity = (string) $request->input('luigi_identity');
        $recommendation_type = trim((string) $request->input('recommendation_type')) ?: 'last_seen';
        $recommender_client_identifier = trim((string) $request->input('recommender_client_identifier')) ?: 'last_seen';

        if (! $trackerId) {
            throw new \RuntimeException('LuigisBox tracker_id not configured');
        }

        $payload = [[
            'blacklisted_item_ids' => [],
            'item_ids' => [$luigi_identity],
            'recommendation_type' => $recommendation_type ,
            'recommender_client_identifier' => $recommender_client_identifier,
            'size' => $size,
            'user_id' => $userId,
            'recommendation_context' => new \stdClass(),
        ]];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json;charset=utf-8',
        ])->post(
            "https://live.luigisbox.tech/v1/recommend?tracker_id={$trackerId}",
            $payload
        );

        if (! $response->successful()) {
            throw new \RuntimeException(
                'Luigi recommender failed: ' . $response->body()
            );
        }

        $json = $response->json();
        $hits = $json[0]['hits'] ?? [];

        $productsInLuigi = collect($hits)
            ->map(function ($hit) {
                $productId = data_get($hit, 'attributes.product_id.0') ?? null;

                return $productId ? [
                    'product_id' => $productId,
                    'web_url'    => data_get($hit, 'attributes.web_url.0')
                ] : null;
            })
            ->filter()
            ->unique()
            ->keyBy('product_id');

        if ($productsInLuigi->isEmpty()) {
            return [];
        }

        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->whereIn('products.id', $productsInLuigi->keys());
        $queryBuilder->where('products.has_live_webpage', true)
            ->select([
                'products.id',
                'products.code',
                'products.name',
                'products.available_quantity',
                'products.price',
                'products.rrp',
                'products.units',
                'products.web_images',
            ]);
        
        $result = $queryBuilder->defaultSort('name')
            ->withIrisPaginator($size)
            ->withQueryString();

        $result->through(function ($product) use ($productsInLuigi) {
            $product->url = $productsInLuigi[$product->id]['web_url'] ?? null;
            return $product;
        });

        return $result;
    }


    public function asController(ActionRequest $request): LengthAwarePaginator|array
    {
        $this->initialisation($request);
        return $this->handle($request);
    }


    public function jsonResponse(LengthAwarePaginator|array $products): array
    {
        return is_array($products) ? $products : IrisLuigiBoxRecommendationResource::collection($products)->toArray(request());
    }
}
