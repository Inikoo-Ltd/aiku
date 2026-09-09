<?php

/*
 * author Louis Perez
 * created on 26-03-2026-16h-58m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\Catalogue\ProductsWebpageResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetProductsInShop extends OrgAction
{
    use WithCatalogueAuthorisation;

    private Shop $parent;

    public function handle(Shop $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('products.name', $value)
                    ->orWhereStartWith('products.code', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->where('products.shop_id', $parent->id);
        $queryBuilder->where('products.is_for_sale', true);

        /*
         * The outer size comes from the stock the product is made of, not from products.units: a
         * dropshipping product sells a single item out of an outer of six and still counts one
         * unit. A product built from more than one SKU has no single outer, so it gets none.
         */
        $queryBuilder->select('products.*')->selectSub(
            DB::table('product_has_org_stocks')
                ->join('org_stocks', 'org_stocks.id', '=', 'product_has_org_stocks.org_stock_id')
                ->selectRaw('case when count(*) = 1 then max(org_stocks.packed_in) end')
                ->whereColumn('product_has_org_stocks.product_id', 'products.id'),
            'packed_in'
        );

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
