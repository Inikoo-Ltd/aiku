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


}
