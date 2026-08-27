<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Actions\IrisAction;
use App\Actions\Web\Website\UpdateWebsiteSearchBoosts;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class SearchIrisCatalogue extends IrisAction
{
    use WithIrisSearchEnrichedItems;

    public function handle(string $query): array
    {
        $boosts     = UpdateWebsiteSearchBoosts::activeBoostIds($this->website);
        $customerId = $this->signedInCustomerId();

        $results = Search::run('catalogue', $query, [
            'shop_id'            => $this->shop->id,
            'is_in_website'      => true,
            'boosts'             => $boosts,
            'language'           => $this->shop->language->code,
            'orders_customer_id' => $customerId,
        ]);

        data_set($results, 'results.products', $this->enrichItems(Arr::get($results, 'results.products', []), Product::class, largeImage: true));
        data_set($results, 'results.product_categories', $this->enrichItems(Arr::get($results, 'results.product_categories', []), ProductCategory::class, largeImage: true));
        data_set($results, 'results.collections', $this->enrichItems(Arr::get($results, 'results.collections', []), Collection::class));

        data_set($results, 'results.best_match', $this->bestMatch($query, Arr::get($results, 'results', [])));

        return $results;
    }

    /**
     * A family beats the top product in the Best Match spotlight when the query is
     * its exact code or name ("jcg" must land on the JCG family, not a JCGB product),
     * or when Typesense scores it above every product hit.
     *
     * @param array<string, array<int, array<string, mixed>>> $results
     *
     * @return array<string, mixed>|null
     */
    protected function bestMatch(string $query, array $results): ?array
    {
        $topCategory = Arr::first(Arr::get($results, 'product_categories', []));
        if (!$topCategory) {
            return null;
        }

        $normalisedQuery = mb_strtolower(trim($query));
        $isExactMatch    = in_array($normalisedQuery, [
            mb_strtolower($topCategory['code'] ?? ''),
            mb_strtolower($topCategory['name'] ?? ''),
        ], true);

        $topProduct = Arr::first(Arr::get($results, 'products', []));

        if ($isExactMatch || Arr::get($topCategory, 'score', 0) > Arr::get($topProduct ?? [], 'score', 0)) {
            return array_merge($topCategory, ['type' => 'product_category']);
        }

        return null;
    }

    public function rules(): array
    {
        return [
            'q'      => ['required', 'string', 'max:100'],
            'source' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);

        $results = $this->handle($this->validatedData['q']);

        $resultsCount = collect(Arr::get($results, 'results', []))->sum(fn ($items) => is_array($items) ? count($items) : 0);

        $armCounts = Arr::pull($results, 'arm_counts');

        $results['search_log_ulid'] = $this->recordWebsiteSearchLog(
            $request,
            'catalogue',
            $this->validatedData['q'],
            $resultsCount,
            $armCounts
        );

        return $results;
    }
}
