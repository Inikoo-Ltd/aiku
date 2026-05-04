<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 May 2026 11:18:32 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Http\Resources\Catalogue\Collection\CollectionSearchResultResource;
use App\Http\Resources\Catalogue\Product\ProductSearchResultResource;
use App\Http\Resources\Catalogue\ProductCategory\ProductCategorySearchResultResource;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchCatalogue
{
    use AsAction;

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


        return [
            'scope'   => 'catalogue',
            'results' => [
                'products'           => ProductSearchResultResource::collection($productsQuery->get()),
                'product_categories' => ProductCategorySearchResultResource::collection($productCategoriesQuery->get()),
                'collections'        => CollectionSearchResultResource::collection($collectionsQuery->get()),
            ],
        ];
    }


}
