<?php

/*
 * author Arya Permana - Kirin
 * created on 07-03-2025-14h-29m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\Pallet\Json;

use App\Actions\OrgAction;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Http\Resources\Fulfilment\PalletsResource;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\QueryBuilder;

class GetFulfilmentCustomerStoringPallets extends OrgAction
{
    public function handle(FulfilmentCustomer $fulfilmentCustomer): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('pallets.customer_reference', $value)
                    ->orWhereWith('pallets.reference', $value)
                    ->orWhereWith('pallets.notes', $value);
            });
        });

        $query = QueryBuilder::for(Pallet::class);


        $query->where('fulfilment_customer_id', $fulfilmentCustomer->id);
        $query->where('pallets.status', PalletStatusEnum::STORING);
        $query->leftjoin('locations', 'pallets.location_id', '=', 'locations.id');

        $query->defaultSort('pallets.id')
            ->select(
                'pallets.id',
                'pallets.slug',
                'pallets.reference',
                'pallets.customer_reference',
                'pallets.notes',
                'pallets.state',
                'pallets.status',
                'pallets.rental_id',
                'pallets.type',
                'pallets.received_at',
                'pallets.location_id',
                'locations.code as location_code',
                'locations.slug as location_slug',
                'pallets.fulfilment_customer_id',
                'pallets.dispatched_at',
                'pallets.warehouse_id',
                'pallets.pallet_delivery_id',
                'pallets.pallet_return_id'
            );


        return $query->allowedSorts(['customer_reference', 'reference', 'dispatched_at'])
                ->allowedFilters([$globalSearch])
                ->withPaginator(null)
                ->withQueryString();
    }



    public function jsonResponse(LengthAwarePaginator $pallets): AnonymousResourceCollection
    {
        return PalletsResource::collection($pallets);
    }

    public function asController(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $request);

        return $this->handle($fulfilmentCustomer);
    }
}
