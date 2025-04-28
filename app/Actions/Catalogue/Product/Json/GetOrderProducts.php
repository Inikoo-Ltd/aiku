<?php

/*
 * author Arya Permana - Kirin
 * created on 06-12-2024-10h-47m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\Catalogue\OrderProductsResource;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Order;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetOrderProducts extends OrgAction
{
    use WithCatalogueAuthorisation;


    public function handle(Order $order, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('products.name', $value)
                    ->orWhereStartWith('products.code', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->leftJoin('transactions', function ($join) use ($order) {
            $join->on('transactions.model_id', '=', 'products.id')
                ->where('transactions.order_id', $order->id);
        });
        $queryBuilder->leftJoin('orders', 'transactions.order_id', 'orders.id');
        $queryBuilder->where('products.shop_id', $order->shop_id)->where('products.is_for_sale', true);
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
                'products.slug',
                'products.available_quantity',
                'transactions.quantity_ordered as quantity_ordered',
                'transactions.id as transaction_id',
                'orders.id as order_id',
            ])
            ->leftJoin('product_stats', 'products.id', 'product_stats.product_id');


        return $queryBuilder->allowedSorts(['code', 'name', 'shop_slug', 'department_slug', 'family_slug'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return OrderProductsResource::collection($products);
    }

    public function asController(Order $order, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle(order: $order);
    }

}
