<?php

/*
 * Author: eka yudinata <ekayudintha@gmail.com>
 * Created: Tue, 21 Apr 2026
 * Copyright (c) 2026, eka yudinata
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
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetProductsForBeefreeSearch extends OrgAction
{
    use WithCatalogueAuthorisation;

    private Shop $parent;

    public function handle(Shop $parent, array $searchData, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('products.code', 'like', '%' . $value . '%');
            });
        });

        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->where('products.shop_id', $parent->id);
        $queryBuilder->where('products.is_for_sale', true);

        // Apply search if provided
        if (!empty($searchData['search'])) {
            $searchValue = $searchData['search'];
            $queryBuilder->where(function ($query) use ($searchValue) {
                $query->where('products.code', 'like', '%' . $searchValue . '%')
                    ->orWhere('products.name', 'like', '%' . $searchValue . '%');
            });
        }

        return $queryBuilder->defaultSort('-id')
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, queryName: 'per_page')
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

        $searchData = [
            'search' => $request->input('search', ''),
        ];

        return $this->handle(parent: $shop, searchData: $searchData);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
