<?php

/*
 * author Louis Perez
 * created on 27-01-2026-09h-22m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\Luigi;

use App\Actions\IrisAction;
use App\Services\QueryBuilder;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use App\Http\Resources\Catalogue\IrisLuigiBoxRecommendationResource;
use App\Http\Resources\Traits\HasPriceMetrics;
use App\Models\Catalogue\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LuigiBoxGetProductDetail extends IrisAction
{

    use HasPriceMetrics;

    public function handle(array $modelData): LengthAwarePaginator
    {
        $productIdString = data_get($modelData, 'product_ids');
        $queryBuilder = QueryBuilder::for(Product::class)
            ->whereIn('products.id', json_decode("[{$productIdString}]"))
             ->leftJoin('webpages', 'webpages.id', '=', 'products.webpage_id');

        $queryBuilder->select([
            'products.id',
            'products.code',
            'products.name',
            'products.available_quantity',
            'products.price',
            'products.rrp',
            'products.web_images',
            'products.unit',
            'products.units',
            'products.offers_data',
            'products.price',
            'webpages.canonical_url as url',
        ]);
        

        return $queryBuilder
            ->withIrisPaginator(25)
            ->withQueryString();
    }

    public function rules(): array
    {
        return [
            'product_ids'   => ['required', 'string'],
        ];
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($this->validatedData);
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return IrisLuigiBoxRecommendationResource::collection($products);
    }
}
