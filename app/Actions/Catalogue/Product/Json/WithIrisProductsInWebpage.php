<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Jun 2025 12:39:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Json;

use App\Http\Resources\Catalogue\IrisProductsInWebpageResource;
use App\Models\Catalogue\Product;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;

trait WithIrisProductsInWebpage
{
    public function getGlobalSearch(): AllowedFilter
    {
        return AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('products.name', $value)
                    ->orWhereStartWith('products.code', $value);
            });
        });
    }

    public function getPriceRangeFilter(): AllowedFilter
    {
        return AllowedFilter::callback('price_range', function ($query, $value) {
            [$min, $max] = explode(',', $value);
            $query->whereBetween('price', [(float)$min, (float)$max]);
        });
    }

    public function getBaseQuery(string $stockMode): QueryBuilder
    {
        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->leftJoin('webpages', function ($join) {
            $join->on('webpages.model_id', '=', 'products.id');
        })->where('webpages.model_type', 'Product');


        $queryBuilder->where('products.is_for_sale', true);
        if ($stockMode == 'in_stock') {
            $queryBuilder->where('products.available_quantity', '>', 0);
        } elseif ($stockMode == 'out_of_stock') {
            $queryBuilder->where('products.available_quantity', '<=', 0);
        }

        return $queryBuilder;
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return IrisProductsInWebpageResource::collection($products);
    }

    public function getAllowedFilters(): array
    {
        $globalSearch     = $this->getGlobalSearch();
        $priceRangeFilter = $this->getPriceRangeFilter();

        return [$globalSearch, $priceRangeFilter];
    }

    public function getSelect(): array
    {
        return [
            'products.id',
            'products.image_id',
            'products.code',
            'products.name',
            'products.available_quantity',
            'products.price',
            'products.rrp',
            'products.state',
            'products.status',
            'products.created_at',
            'products.updated_at',
            'products.units',
            'products.unit',
            'products.top_seller',
            'products.web_images',
            'webpages.url'
        ];
    }

    public function getAllowedSorts(): array
    {
        return [
            'price',
            'created_at',
            'available_quantity',
            'code',
            'name'
        ];
    }


    public function getData($queryBuilder)
    {
        return $queryBuilder->defaultSort('name')
            ->allowedSorts($this->getAllowedSorts())
            ->allowedFilters($this->getAllowedFilters())
            ->withIrisPaginator()
            ->withQueryString();
    }

}
