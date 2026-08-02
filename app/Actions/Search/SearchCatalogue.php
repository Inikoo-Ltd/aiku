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

class SearchCatalogue
{
    use AsAction;
    use WithRawSearchResults;

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

        $boosts = Arr::get($options, 'boosts', []);
        $this->applyBoosts($productsQuery, Arr::get($boosts, 'product'));
        $this->applyBoosts($productCategoriesQuery, Arr::get($boosts, 'product_category'));
        $this->applyBoosts($collectionsQuery, Arr::get($boosts, 'collection'));

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
     * ranks them first without pinning them into unrelated searches.
     *
     * @param array<int, int>|null $ids
     */
    private function applyBoosts(Builder $searchQuery, ?array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $searchQuery->options([
            'sort_by' => '_eval(id:['.implode(',', $ids).']):desc,_text_match:desc',
        ]);
    }
}
