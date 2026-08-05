<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 May 2026 11:18:32 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class SearchCatalogue
{
    use AsAction;
    use WithRawSearchResults;
    use WithTypesenseApi;

    /**
     * Typo-recovery tuning driven by the real no-result queries in the search logs:
     * split_join_tokens rescues glued words ("aromcandles" -> "arom candles") and
     * min_len_2typo 7 lets shorter words carry two typos ("auaura" -> "aura").
     *
     * min_len_2typo was 6; 7 was measured against the 696 real trending queries in
     * devops/search/trending/aw.csv and cost nothing - no query lost its results and
     * no top hit moved - while halving the junk served to no-result queries. 8 starts
     * costing real traffic.
     */
    public const array SEARCH_TUNING = [
        'split_join_tokens'     => 'always',
        'typo_tokens_threshold' => 2,
        'min_len_2typo'         => 7,
    ];

    /**
     * result key => [model class, boost type, hits limit]
     */
    protected const array SEARCH_TARGETS = [
        'products'           => [Product::class, 'product', 11],
        'product_categories' => [ProductCategory::class, 'product_category', 10],
        'collections'        => [Collection::class, 'collection', 10],
    ];

    public function handle(string $query, array $options): array
    {
        if (config('scout.driver') === 'typesense') {
            try {
                $hits = $this->multiSearch($query, $options);
            } catch (Throwable) {
                $hits = $this->scoutSearch($query, $options);
            }
        } else {
            $hits = $this->scoutSearch($query, $options);
        }

        $mapCatalogueItem = static fn (array $document) => [
            'id'    => (int)$document['id'],
            'code'  => $document['code'] ?? null,
            'name'  => $document['name'] ?? null,
            'image' => json_decode($document['image'] ?? 'null', true),
        ];

        return [
            'scope'       => 'catalogue',
            'results'     => array_map(
                static fn (array $collectionHits) => array_map(
                    $mapCatalogueItem,
                    Arr::pluck($collectionHits, 'document')
                ),
                $hits
            ),
            'arm_counts'  => $this->sumArmCounts(array_map(
                fn (array $collectionHits) => $this->armCounts($collectionHits),
                $hits
            )),
        ];
    }

    /**
     * All three collections in one HTTP round trip instead of three sequential ones.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function multiSearch(string $query, array $options): array
    {
        $searches = [];
        foreach (self::SEARCH_TARGETS as [$modelClass, $boostType, $limit]) {
            $filters = [];
            if ($shopId = Arr::get($options, 'shop_id')) {
                $filters[] = "shop_id:=$shopId";
            }
            if (Arr::get($options, 'is_in_website')) {
                $filters[] = 'is_in_website:=true';
            }

            $searches[] = array_merge(
                [
                    'collection'             => (new $modelClass())->searchableAs(),
                    'q'                      => $query,
                    'query_by'               => config("scout.typesense.model-settings.$modelClass.search-parameters.query_by") ?? '',
                    'filter_by'              => implode(' && ', $filters),
                    'per_page'               => $limit,
                    'page'                   => 1,
                    'prioritize_exact_match' => true,
                    'enable_overrides'       => true,
                    'prefix'                 => true,
                ],
                $this->extraSearchOptions(Arr::get($options, "boosts.$boostType"), Arr::get($options, 'language'))
            );
        }

        $response = $this->typesenseClient()
            ->post($this->typesenseUrl().'/multi_search', ['searches' => $searches])
            ->throw();

        $hits = [];
        foreach (array_keys(self::SEARCH_TARGETS) as $index => $key) {
            if ($error = $response->json("results.$index.error")) {
                logger()->warning("Typesense multi_search $key failed: $error");
            }
            $hits[$key] = $response->json("results.$index.hits", []);
        }

        return $hits;
    }

    /**
     * Fallback for non-typesense drivers (tests) and typesense outages.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function scoutSearch(string $query, array $options): array
    {
        $hits = [];
        foreach (self::SEARCH_TARGETS as $key => [$modelClass, $boostType, $limit]) {
            $searchQuery = $modelClass::search($query);

            if ($shopId = Arr::get($options, 'shop_id')) {
                $searchQuery->where('shop_id', $shopId);
            }
            if (Arr::get($options, 'is_in_website')) {
                $searchQuery->where('is_in_website', true);
            }

            $searchQuery->options($this->extraSearchOptions(
                Arr::get($options, "boosts.$boostType"),
                Arr::get($options, 'language')
            ));
            $searchQuery->take($limit);

            $hits[$key] = $this->rawHits($searchQuery);
        }

        return $hits;
    }

    /**
     * Boosted items float to the top of the results they already match: an _eval sort
     * ranks them first without pinning them into unrelated searches.
     *
     * @param array<int, int>|null $boostIds
     *
     * @return array<string, mixed>
     */
    private function extraSearchOptions(?array $boostIds, ?string $languageCode): array
    {
        $searchOptions = self::SEARCH_TUNING;

        if ($languageCode && $this->synonymSetExists($languageCode)) {
            $searchOptions['synonym_sets'] = StoreSearchSynonym::synonymSet($languageCode);
        }

        if (!empty($boostIds)) {
            $searchOptions['sort_by'] = '_eval(id:['.implode(',', $boostIds).']):desc,_text_match:desc';
        }

        return $searchOptions;
    }

    /**
     * Referencing a missing synonym set makes Typesense fail the whole search with
     * a 404, so the set is only attached once it exists. Cached briefly; the cache
     * is invalidated by StoreSearchSynonym so new sets apply immediately.
     */
    private function synonymSetExists(string $languageCode): bool
    {
        return (bool)cache()->remember(
            StoreSearchSynonym::synonymSetExistsCacheKey($languageCode),
            300,
            function () use ($languageCode) {
                try {
                    return $this->typesenseClient()
                        ->get($this->typesenseUrl().'/synonym_sets/'.StoreSearchSynonym::synonymSet($languageCode))
                        ->successful();
                } catch (Throwable) {
                    return false;
                }
            }
        );
    }
}
