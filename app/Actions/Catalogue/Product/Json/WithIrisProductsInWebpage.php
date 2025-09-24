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
            if (is_array($value)) {
                [$min, $max] = $value;
            } else {
                [$min, $max] = explode(',', $value);
            }
            $min = (float)$min;
            $max = (float)$max;

            if ($max == 0) {
                $query->where('price', '>=', $min);
            } else {
                $query->whereBetween('price', [$min, $max]);
            }
        });
    }

    public function getNewArrivalsFilter(): AllowedFilter
    {
        return AllowedFilter::callback('new_arrivals', function ($query, $value) {
            $days = is_numeric($value) ? (int) $value : 3;
            $query->where('products.created_at', '>=', now()->subDays($days));
        });
    }

    public function getBrandsFilter(): AllowedFilter
    {
        return AllowedFilter::callback('brands', function ($query, $value) {
            $query->join('model_has_brands', function ($join) {
                $join->on('trade_units.id', '=', 'model_has_brands.model_id')
                        ->where('model_has_brands.model_type', 'TradeUnit');
            });

            $query->join('brands', 'brands.id', 'model_has_brands.brand_id');

            $query->whereIn('brands.id', (array) $value);
        });
    }

    public function getTagsFilter(): AllowedFilter
    {
        return AllowedFilter::callback('tags', function ($query, $value) {
            $query->join('model_has_tags', function ($join) {
                $join->on('trade_units.id', '=', 'model_has_tags.model_id')
                        ->where('model_has_tags.model_type', 'TradeUnit');
            });

            $query->join('tags', 'tags.id', 'model_has_tags.tag_id');

            $query->whereIn('tags.id', (array) $value);
        });
    }

    public function getBaseQuery(string $stockMode): QueryBuilder
    {
        $customer = request()->user()?->customer;
        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->leftJoin('webpages', 'webpages.id', '=', 'products.webpage_id');

        $queryBuilder->where('products.is_for_sale', true);
        if ($stockMode == 'in_stock') {
            $queryBuilder->where('products.available_quantity', '>', 0);
        } elseif ($stockMode == 'out_of_stock') {
            $queryBuilder->where('products.available_quantity', '<=', 0);
        }

        $queryBuilder->join('model_has_trade_units', function ($join) {
            $join->on('products.id', '=', 'model_has_trade_units.model_id')
                ->where('model_has_trade_units.model_type', 'Product');
        });
        $queryBuilder->join('trade_units', 'trade_units.id', 'model_has_trade_units.trade_unit_id');

        if ($customer) {
            $basket = $customer->orderInBasket;

            if ($basket) {
                $queryBuilder->leftjoin('transactions', function ($join) use ($basket) {
                    $join->on('transactions.model_id', '=', 'products.id')
                        ->where('transactions.model_type', '=', 'Product')
                        ->where('transactions.order_id', '=', $basket->id)
                        ->whereNull('transactions.deleted_at');
                });
            }
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
        $newArrivalsFilter = $this->getNewArrivalsFilter();
        $brandsFilter = $this->getBrandsFilter();
        $tagsFilter = $this->getTagsFilter();

        return [$globalSearch, $priceRangeFilter, $newArrivalsFilter, $brandsFilter, $tagsFilter];
    }

    public function getSelect(): array
    {
        $select = [
            'products.id',
            'products.image_id',
            'products.code',
            'products.group_id',
            'products.organisation_id',
            'products.shop_id',
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

        $customer = request()->user()?->customer;
        if ($customer && $customer->orderInBasket) {
            $select[] = 'transactions.id as transaction_id';
            $select[] = 'transactions.quantity_ordered as quantity_ordered';
        }

        return $select;
    }
    public function getAllowedSorts(): array
    {
        return [
            'price',
            'created_at',
            'available_quantity',
            'code',
            'name',
            'rrp'
        ];
    }


    public function getData($queryBuilder, ?int $numberOfRecords = null): LengthAwarePaginator
    {
        return $queryBuilder->defaultSort('name')
            ->allowedSorts($this->getAllowedSorts())
            ->allowedFilters($this->getAllowedFilters())
            ->withIrisPaginator($numberOfRecords)
            ->withQueryString();
    }

}
