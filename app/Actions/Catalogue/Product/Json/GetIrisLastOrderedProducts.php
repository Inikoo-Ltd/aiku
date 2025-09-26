<?php

/*
 * author Arya Permana - Kirin
 * created on 06-12-2024-10h-47m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Http\Resources\Catalogue\LastOrderedProductsResource;
use App\Http\Resources\Catalogue\OrderProductsResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Ordering\Order;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetIrisLastOrderedProducts extends IrisAction
{
    use WithCatalogueAuthorisation;


    public function handle(ProductCategory $productCategory, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('products.name', $value)
                    ->orWhereStartWith('products.code', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->where('products.family_id'. $productCategory->id);
        $queryBuilder->leftJoin('transactions', function ($join) {
            $join->on('transactions.model_id', '=', 'products.id')
                ->where('transactions.model_type', 'Product')
                ->where('transactions.state', TransactionStateEnum::SUBMITTED)
                ->whereRaw('transactions.submitted_at = (
                    SELECT MAX(t2.submitted_at) 
                    FROM transactions t2 
                    WHERE t2.model_id = products.id 
                    AND t2.model_type = "Product" 
                    AND t2.state = ?
                )', [TransactionStateEnum::SUBMITTED]);
        });
       
        $queryBuilder
            ->defaultSort('products.code')
            ->select([
                'products.id',
                'products.current_historic_asset_id',
                'products.asset_id',
                'products.code',
                'products.name',
                'products.state',
                'products.created_at',
                'products.updated_at',
                'products.price',
                'products.slug',
                'products.available_quantity'
            ])
            ->leftJoin('product_stats', 'products.id', 'product_stats.product_id');


        return $queryBuilder->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return LastOrderedProductsResource::collection($products);
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle(productCategory: $productCategory);
    }

}
