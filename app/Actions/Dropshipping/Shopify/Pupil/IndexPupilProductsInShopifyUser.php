<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Pupil;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Http\Resources\Catalogue\ProductResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\ShopifyUser;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPupilProductsInShopifyUser
{
    use AsAction;
    use WithAttributes;

    /**
     * @var \App\Models\Dropshipping\ShopifyUser
     */
    private ShopifyUser $parent;

    /**
     * @throws \Exception
     */
    public function handle(Shop $shop)
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('products.name', $value)
                    ->orWhereStartWith('products.code', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(Product::class);

        $queryBuilder->where('shop_id', $shop->id);
        $queryBuilder->where('state', ProductStateEnum::ACTIVE->value);
        $queryBuilder->whereNotIn(
            'id',
            $this->parent->products()->where('shopify_user_id', $this->parent->id)
                ->pluck('product_id')
        );

        $queryBuilder
            ->defaultSort('products.code');

        return $queryBuilder->allowedSorts(['code', 'name', 'shop_slug'])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null)
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return ProductResource::collection($products);
    }

    /**
     * @throws \Exception
     */
    public function asController(ShopifyUser $shopifyUser)
    {
        $this->parent = $shopifyUser;
        $shop         = $shopifyUser->customer->shop;

        return $this->handle($shop);
    }
}
