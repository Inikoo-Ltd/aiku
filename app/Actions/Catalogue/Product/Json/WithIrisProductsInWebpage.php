<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Jun 2025 12:39:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Json;

use App\Http\Resources\Catalogue\IrisProductsInWebpageResource;
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

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return IrisProductsInWebpageResource::collection($products);
    }
}
