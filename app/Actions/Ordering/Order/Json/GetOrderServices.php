<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jul 2024 13:54:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingAuthorisation;
use App\Http\Resources\Ordering\OrderServicesResource;
use App\Models\Billables\Service;
use App\Models\Ordering\Order;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetOrderServices extends OrgAction
{
    use WithOrderingAuthorisation;

    public function handle(Order $order, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('services.name', $value)
                    ->orWhereStartWith('services.code', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(Service::class);
        $queryBuilder->leftJoin('transactions', function ($join) use ($order) {
            $join->on('transactions.model_id', '=', 'services.id')
                ->where('transactions.model_type', 'Service')
                ->where('transactions.order_id', $order->id)
                ->whereNull('transactions.deleted_at');
        });
        $queryBuilder->where('services.shop_id', $order->shop_id)
            ->where('services.state', 'active');
        $queryBuilder->join('assets', 'services.asset_id', '=', 'assets.id');
        $queryBuilder->join('currencies', 'assets.currency_id', '=', 'currencies.id');

        $queryBuilder
            ->defaultSort('services.code')
            ->select([
                'services.id',
                'services.slug',
                'services.state',
                'services.created_at',
                'services.price',
                'services.unit',
                'assets.name',
                'assets.code',
                'assets.current_historic_asset_id as historic_asset_id',
                'services.description',
                'currencies.code as currency_code',
                'transactions.quantity_ordered as quantity_ordered',
                'transactions.id as transaction_id',
                'transactions.order_id',
            ]);

        return $queryBuilder
            ->allowedSorts(['code', 'name', 'price'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $services): AnonymousResourceCollection
    {
        return OrderServicesResource::collection($services);
    }

    public function asController(Order $order, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order);
    }
}
