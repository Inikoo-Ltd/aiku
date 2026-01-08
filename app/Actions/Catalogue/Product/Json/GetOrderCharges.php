<?php

/*
 * author Louis Perez
 * created on 08-01-2026-13h-21m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Http\Resources\Sales\ChargeInOrderResource;
use App\Models\Billables\Charge;
use App\Models\Ordering\Order;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetOrderCharges extends OrgAction
{
    use WithOrderingEditAuthorisation;

    public function handle(Order $order, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('products.name', $value)
                    ->orWhereStartWith('products.code', $value);
            });
        });
        
        $shop = $order->shop;

        $queryBuilder = QueryBuilder::for(Charge::class);
        $queryBuilder
            ->where('charges.shop_id', $shop->id)
            ->where('charges.state', ChargeStateEnum::ACTIVE);
        
        $queryBuilder
            ->leftJoin('transactions', function ($join) {
                $join
                    ->on('transactions.model_id', 'charges.id')
                    ->where('transactions.model_type', Charge::class);
            });

        return $queryBuilder
            ->defaultSort('charges.code')
            ->select([
                'charges.type',
                'charges.code',
                'charges.name',
                'charges.description',
                'charges.settings',
                'charges.currency_id',
                'transactions.net_amount',
                'transactions.state as transaction_state'
            ])
            ->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $chargeTransaction): AnonymousResourceCollection
    {
        return ChargeInOrderResource::collection($chargeTransaction);
    }

    public function asController(Order $order, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle(order: $order);
    }

}
