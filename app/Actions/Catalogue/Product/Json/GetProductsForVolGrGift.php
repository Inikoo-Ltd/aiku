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
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Http\Resources\Discounts\ProductsForVolGrGiftResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Traits\HasSearchableText;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetProductsForVolGrGift extends OrgAction
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
        $queryBuilder->whereIn('products.state', [ProductStateEnum::ACTIVE, ProductStateEnum::DISCONTINUING]);
        $queryBuilder->select([
            'products.id',
            'products.code',
            'products.name',
            'products.slug',
            'products.available_quantity',
            'products.state',
            'products.web_images',
        ]);

        return $queryBuilder->defaultSort('-id')
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return ProductsForVolGrGiftResource::collection($products);
    }

    public function htmlResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return ProductsForVolGrGiftResource::collection($products);
    }

    public function asController(Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $shop);
    }

}
