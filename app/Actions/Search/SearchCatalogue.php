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
     * min_len_2typo 6 lets shorter words carry two typos ("auaura" -> "aura").
     */
    public const array SEARCH_TUNING = [
        'split_join_tokens'     => 'always',
        'typo_tokens_threshold' => 2,
        'min_len_2typo'         => 6,
    ];

    public function handle(string $query, array $options): array
    {
        $productsQuery          = Product::search($query);
        $productCategoriesQuery = ProductCategory::search($query);
        $collectionsQuery       = Collection::search($query);
        if ($shopId = Arr::get($options, 'shop_id')) {
            $productsQuery->where('shop_id', $shopId);
            $productCategoriesQuery->where('shop_id', $shopId);
            $collectionsQuery->where('shop_id', $shopId);
        }

        if (Arr::get($options, 'is_in_website')) {
            $productsQuery->where('is_in_website', true);
            $productCategoriesQuery->where('is_in_website', true);
            $collectionsQuery->where('is_in_website', true);
        }

        $boosts   = Arr::get($options, 'boosts', []);
        $language = Arr::get($options, 'language');
        $this->applySearchOptions($productsQuery, Arr::get($boosts, 'product'), $language);
        $this->applySearchOptions($productCategoriesQuery, Arr::get($boosts, 'product_category'), $language);
        $this->applySearchOptions($collectionsQuery, Arr::get($boosts, 'collection'), $language);

        $productsQuery->take(11);
        $productCategoriesQuery->take(10);
        $collectionsQuery->take(10);

        $mapCatalogueItem = static fn (array $document) => [
            'id'    => (int)$document['id'],
            'code'  => $document['code'] ?? null,
            'name'  => $document['name'] ?? null,
            'image' => json_decode($document['image'] ?? 'null', true),
        ];

        return [
            'scope'   => 'catalogue',
            'results' => [
                'products'           => array_map($mapCatalogueItem, $this->rawDocuments($productsQuery)),
                'product_categories' => array_map($mapCatalogueItem, $this->rawDocuments($productCategoriesQuery)),
                'collections'        => array_map($mapCatalogueItem, $this->rawDocuments($collectionsQuery)),
            ],
        ];
    }

    /**
     * Boosted items float to the top of the results they already match: an _eval sort
     * ranks them first without pinning them into unrelated searches. Builder::options()
     * replaces rather than merges, so the tuning and the boost sort are set together.
     *
     * @param array<int, int>|null $boostIds
     */
    private function applySearchOptions(Builder $searchQuery, ?array $boostIds, ?string $languageCode): void
    {
        $searchOptions = self::SEARCH_TUNING;

        if ($languageCode && $this->synonymSetExists($languageCode)) {
            $searchOptions['synonym_sets'] = StoreSearchSynonym::synonymSet($languageCode);
        }

        if (!empty($boostIds)) {
            $searchOptions['sort_by'] = '_eval(id:['.implode(',', $boostIds).']):desc,_text_match:desc';
        }

        $searchQuery->options($searchOptions);
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
