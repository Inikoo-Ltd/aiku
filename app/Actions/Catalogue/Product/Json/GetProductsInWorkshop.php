<?php

/*
 * author Arya Permana - Kirin
 * created on 16-06-2025-15h-20m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\Catalogue\ProductsWebpageResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Traits\HasSearchableText;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetProductsInWorkshop extends OrgAction
{
    use WithCatalogueAuthorisation;
    use HasSearchableText;

    private Shop $parent;

    public function handle(Shop $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $normalizedValue = $this->normalizeSearchableText($value);

                // Ignore if search token is less than 2 words
                $searchTokens = array_values(array_filter(
                    explode(' ', trim($normalizedValue)),
                    fn ($t) => strlen($t) >= 2
                ));

                foreach ($searchTokens as $searchToken) {
                    $query->where('searchable_text', 'ILIKE', "% {$searchToken}%");
                }
            });
        });

        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->where('products.shop_id', $parent->id);
        $queryBuilder->where('products.is_for_sale', true);
        $queryBuilder->where('products.available_quantity', '>', 0);

        return $queryBuilder->defaultSort('-id')
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return ProductsWebpageResource::collection($products);
    }

    public function asController(Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $shop);
    }

}
