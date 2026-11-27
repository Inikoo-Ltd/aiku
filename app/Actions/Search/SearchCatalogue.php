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
use Laravel\Scout\Builder;
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
     * Typesense drops words from a query nothing fully matches and searches again with the
     * rest, which matched stray marketing copy: "big garden statue" served Bluetooth speakers
     * whose descriptions say "big sound" and "in the garden". The first pass keeps every
     * word; only when it finds nothing does a second pass drop words, and then only against
     * the item's own code and name.
     */
    public const array STRICT_SEARCH = [
        'drop_tokens_threshold' => 0,
    ];

    public const array FALLBACK_FIELDS = ['code', 'name', 'name_translations'];

    /**
     * Description fields still find an item whose name lacks the words, but a word met in
     * passing in the copy must not outrank the same word in another item's name.
     */
    public const array FIELD_WEIGHTS = [
        'code'                     => 15,
        'name'                     => 15,
        'name_translations'        => 15,
        'barcode'                  => 12,
        'variant_codes'            => 10,
        'description'              => 2,
        'description_extra'        => 1,
        'description_translations' => 1,
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
                ['hits' => $hits, 'strict_hits' => $strictHits] = $this->multiSearch($query, $options);
            } catch (Throwable) {
                ['hits' => $hits, 'strict_hits' => $strictHits] = $this->scoutSearch($query, $options);
            }
        } else {
            ['hits' => $hits, 'strict_hits' => $strictHits] = $this->scoutSearch($query, $options);
        }

        $mapCatalogueItem = static fn (array $hit) => [
            'id'    => (int)$hit['document']['id'],
            'code'  => $hit['document']['code'] ?? null,
            'name'  => $hit['document']['name'] ?? null,
            'state' => $hit['document']['state'] ?? null,
            'image' => json_decode($hit['document']['image'] ?? 'null', true),
            'score' => (int)($hit['text_match'] ?? 0),
        ];

        $results = array_map(
            static fn (array $collectionHits) => array_map($mapCatalogueItem, $collectionHits),
            $hits
        );

        if (!empty($results['products'])) {
            $availableQuantities = Product::whereIn('id', array_column($results['products'], 'id'))
                ->pluck('available_quantity', 'id');
            foreach ($results['products'] as &$product) {
                $product['available_quantity'] = $availableQuantities[$product['id']] ?? null;
            }
            unset($product);
        }

        return [
            'scope'       => 'catalogue',
            'results'     => $results,
            'arm_counts'  => $this->sumArmCounts(array_map(
                fn (array $collectionHits) => $this->armCounts($collectionHits),
                $strictHits
            )),
        ];
    }

    /**
     * All three collections in one HTTP round trip instead of three sequential ones; the
     * word-dropping fallback costs a second round trip only for collections that found nothing.
     * strict_hits feed the assortment-gap counts: a query only the fallback answered is still a gap.
     *
     * @return array{hits: array<string, array<int, array<string, mixed>>>, strict_hits: array<string, array<int, array<string, mixed>>>}
     */
    private function multiSearch(string $query, array $options): array
    {
        $strictSearches = [];
        foreach (self::SEARCH_TARGETS as $key => [$modelClass, $boostType, $limit]) {
            $strictSearches[$key] = array_merge(
                $this->typesenseSearchParameters($query, $options, $modelClass, $boostType, $limit),
                self::strictSearchOptions(self::queryBy($modelClass, Arr::get($options, 'translate_to')))
            );
        }

        $strictHits = $this->runMultiSearch($strictSearches);
        $hits       = $strictHits;

        $fallbackSearches = [];
        if (self::allowsFallback($query)) {
            foreach (self::SEARCH_TARGETS as $key => [$modelClass, $boostType, $limit]) {
                if (empty($hits[$key])) {
                    $fallbackSearches[$key] = array_merge(
                        $this->typesenseSearchParameters($query, $options, $modelClass, $boostType, $limit),
                        self::fallbackSearchOptions(self::queryBy($modelClass, Arr::get($options, 'translate_to')))
                    );
                }
            }
        }

        if ($fallbackSearches) {
            foreach ($this->runMultiSearch($fallbackSearches) as $key => $fallbackHits) {
                $hits[$key] = self::fallbackHits($fallbackHits, $query);
            }
        }

        return ['hits' => $hits, 'strict_hits' => $strictHits];
    }

    /**
     * @return array<string, mixed>
     */
    private function typesenseSearchParameters(string $query, array $options, string $modelClass, string $boostType, int $limit): array
    {
        $filters = [];
        if ($shopId = Arr::get($options, 'shop_id')) {
            $filters[] = "shop_id:=$shopId";
        }
        if (Arr::get($options, 'is_in_website')) {
            $filters[] = 'is_in_website:=true';
        }

        return array_merge(
            [
                'collection'             => (new $modelClass())->searchableAs(),
                'q'                      => $query,
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

    /**
     * @param array<string, array<string, mixed>> $searches
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function runMultiSearch(array $searches): array
    {
        $response = $this->typesenseClient()
            ->post($this->typesenseUrl().'/multi_search', ['searches' => array_values($searches)])
            ->throw();

        $hits = [];
        foreach (array_keys($searches) as $index => $key) {
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
     * @return array{hits: array<string, array<int, array<string, mixed>>>, strict_hits: array<string, array<int, array<string, mixed>>>}
     */
    private function scoutSearch(string $query, array $options): array
    {
        $hits       = [];
        $strictHits = [];
        foreach (self::SEARCH_TARGETS as $key => [$modelClass, $boostType, $limit]) {
            $queryBy = self::queryBy($modelClass, Arr::get($options, 'translate_to'));

            $strictHits[$key] = $this->rawHits($this->scoutQuery($query, $options, $modelClass, $boostType, $limit, self::strictSearchOptions($queryBy)));
            $hits[$key]       = $strictHits[$key];

            if (empty($hits[$key]) && self::allowsFallback($query)) {
                $hits[$key] = self::fallbackHits(
                    $this->rawHits($this->scoutQuery($query, $options, $modelClass, $boostType, $limit, self::fallbackSearchOptions($queryBy))),
                    $query
                );
            }
        }

        return ['hits' => $hits, 'strict_hits' => $strictHits];
    }

    /**
     * @param array<string, mixed> $passOptions
     */
    private function scoutQuery(string $query, array $options, string $modelClass, string $boostType, int $limit, array $passOptions): Builder
    {
        $searchQuery = $modelClass::search($query);

        if ($shopId = Arr::get($options, 'shop_id')) {
            $searchQuery->where('shop_id', $shopId);
        }
        if (Arr::get($options, 'is_in_website')) {
            $searchQuery->where('is_in_website', true);
        }

        $searchQuery->options(array_merge(
            $this->extraSearchOptions(Arr::get($options, "boosts.$boostType"), Arr::get($options, 'language')),
            $passOptions
        ));

        return $searchQuery->take($limit);
    }

    /**
     * @return array<string, mixed>
     */
    public static function strictSearchOptions(string $queryBy): array
    {
        return array_merge(self::STRICT_SEARCH, [
            'query_by'         => $queryBy,
            'query_by_weights' => self::queryByWeights($queryBy),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fallbackSearchOptions(string $queryBy): array
    {
        $fallbackQueryBy = implode(',', array_values(array_intersect(explode(',', $queryBy), self::FALLBACK_FIELDS)));

        return [
            'query_by'         => $fallbackQueryBy,
            'query_by_weights' => self::queryByWeights($fallbackQueryBy),
        ];
    }

    public static function queryByWeights(string $queryBy): string
    {
        return implode(',', array_map(
            static fn (string $field) => self::FIELD_WEIGHTS[$field] ?? 1,
            explode(',', $queryBy)
        ));
    }

    public static function allowsFallback(string $query): bool
    {
        return count(self::queryWords($query)) > 1;
    }

    /**
     * A word-dropping hit must still carry at least half the words typed: "big" alone is
     * no answer to "big garden statue".
     *
     * @param array<int, array<string, mixed>> $hits
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fallbackHits(array $hits, string $query): array
    {
        $minimumWords = (int)ceil(count(self::queryWords($query)) / 2);

        return array_values(array_filter(
            $hits,
            static fn (array $hit) => (int)Arr::get($hit, 'text_match_info.tokens_matched', 0) >= $minimumWords
        ));
    }

    /**
     * @return array<int, string>
     */
    private static function queryWords(string $query): array
    {
        return preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * A visitor reading the website in another language (INI-510) also searches the translated
     * names and descriptions; every other search keeps the shop's own fields only.
     */
    public static function queryBy(string $modelClass, ?string $translateTo): string
    {
        $queryBy = config("scout.typesense.model-settings.$modelClass.search-parameters.query_by") ?? '';

        if ($translateTo && in_array($modelClass, [Product::class, ProductCategory::class], true) && static::make()->translationsAreIndexed($modelClass)) {
            $queryBy .= ',name_translations,description_translations';
        }

        return $queryBy;
    }

    public static function translationsIndexedCacheKey(string $modelClass): string
    {
        return 'search:translations_indexed:'.(new $modelClass())->searchableAs();
    }

    /**
     * Typesense fails a search whose query_by names a field the collection lacks, so the translated
     * fields are only searched once reindex_search:catalogue_translations has added them.
     */
    public function translationsAreIndexed(string $modelClass): bool
    {
        if (config('scout.driver') !== 'typesense') {
            return true;
        }

        return (bool)cache()->remember(
            self::translationsIndexedCacheKey($modelClass),
            300,
            function () use ($modelClass) {
                try {
                    $fields = $this->typesenseClient()
                        ->get($this->typesenseUrl().'/collections/'.(new $modelClass())->searchableAs())
                        ->json('fields', []);

                    return in_array('description_translations', array_column($fields, 'name'), true);
                } catch (Throwable) {
                    return false;
                }
            }
        );
    }

    /**
     * Every language of an *_i8n column in one searchable list: the query string already says which
     * language the visitor typed in, so the index does not need a field per language.
     *
     * @param array<string, mixed> $translations
     *
     * @return array<int, string>
     */
    public static function searchableTranslations(array $translations): array
    {
        return array_values(array_unique(array_filter(
            $translations,
            static fn ($text) => is_string($text) && trim($text) !== ''
        )));
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
