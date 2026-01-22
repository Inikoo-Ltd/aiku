<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Sept 2025 11:33:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Luigi;

use App\Actions\Catalogue\Product\Json\WithIrisProductsInWebpage;
use App\Actions\IrisAction;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\Catalogue\IrisAuthenticatedProductsInWebpageResource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class LuigiLastSeen extends IrisAction
{
    use WithIrisProductsInWebpage;

    public function handle(ActionRequest $request): LengthAwarePaginator|array
    {
        $customer = $request->user();
        $size = 25;
        $userId = $customer?->customer_id
            ? (string) $customer->customer_id
            : (string) ($request->cookie('_lb') ?? 'guest');

        $website   = $request->get('website');
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

        $productIds = collect($hits)
            ->map(function ($hit) {
                $productId = $hit['attributes']['product_id'] ?? null;

                if (is_array($productId)) {
                    return $productId[0] ?? null;
                }

                return is_string($productId) ? $productId : null;
            })
            ->filter()
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        $queryBuilder = $this->getBaseQuery('all', false);

        $queryBuilder->whereExists(function ($q) {
            $q->select(DB::raw(1))
                ->from('webpages')
                ->whereColumn('webpages.id', 'products.webpage_id')
                ->where('webpages.state', 'live');
        });

        $queryBuilder->where(function ($query) {
            $query
                ->whereNull('products.variant_id')
                ->orWhere('products.is_variant_leader', true);
        });

        $queryBuilder->select($this->getSelect());

        $queryBuilder->addSelect([
            DB::raw('products.variant_id IS NOT NULL as is_variant'),
            DB::raw('exists (
            select 1
            from org_stocks os
            join product_has_org_stocks phos on phos.org_stock_id = os.id
            where phos.product_id = products.id
              and os.is_on_demand = true
        ) as is_on_demand'),
        ]);

        $queryBuilder
            ->whereIn('products.id', $productIds);

        return $this
            ->getData($queryBuilder,  $size);
    }


    public function asController(ActionRequest $request): LengthAwarePaginator|array
    {
        $this->initialisation($request);
        return $this->handle($request);
    }


    public function jsonResponse(LengthAwarePaginator|array $products): array
    {
        return is_array($products) ? $products : IrisAuthenticatedProductsInWebpageResource::collection($products)->toArray(request());
    }
}
