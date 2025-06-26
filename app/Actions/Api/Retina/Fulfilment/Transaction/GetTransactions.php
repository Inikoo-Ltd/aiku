<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Fulfilment\Transaction;

use App\Actions\Api\Retina\Fulfilment\Resource\SKUOrderApiResource;
use App\Actions\RetinaApiAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletStoredItem;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class GetTransactions extends RetinaApiAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel, PalletReturn $palletReturn, array $modelData): LengthAwarePaginator
    {

        $queryBuilder = QueryBuilder::for(PalletStoredItem::class)
        ->leftJoin('stored_items', 'pallet_stored_items.stored_item_id', '=', 'stored_items.id')
        ->leftJoin('pallets', 'pallet_stored_items.pallet_id', '=', 'pallets.id')
        ->leftJoin('pallet_return_items', 'pallet_stored_items.id', '=', 'pallet_return_items.pallet_stored_item_id');

        $queryBuilder->where('pallets.fulfilment_customer_id', $customerSalesChannel->customer->fulfilmentCustomer->id);
        $queryBuilder->where('pallet_return_items.pallet_return_id', $palletReturn->id);

        if (Arr::get($modelData, 'reference')) {
            $this->getReferenceSearch($queryBuilder, Arr::get($modelData, 'reference'));
        }

        $queryBuilder
            ->defaultSort('stored_items.id')
            ->select([
                'stored_items.id as stored_item_id',
                'stored_items.reference',
                'stored_items.slug',
                'stored_items.name',
                'stored_items.total_quantity',
                'pallet_return_items.quantity_ordered',
                'pallet_stored_items.id'
            ]);

        return $queryBuilder
            ->allowedSorts(['reference', 'code', 'price', 'name', 'state'])
            ->withBetweenDates(['date'])
            ->withPaginator(null, queryName: 'per_page')
            ->withQueryString();
    }

    public function getReferenceSearch($query, string $ref): QueryBuilder
    {
        return $query->where(function ($query) use ($ref) {
            $query->where('stored_items.reference', $ref);
        });
    }

    public function asController(PalletReturn $palletReturn, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($request);
        return $this->handle($this->customerSalesChannel, $palletReturn, $this->validateAttributes());
    }


    public function jsonResponse(LengthAwarePaginator $orders): AnonymousResourceCollection
    {
        return SKUOrderApiResource::collection($orders);
    }

    public function rules(): array
    {
        return [
            'reference' => ['nullable', 'string'],
            'page' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer'],
            'sort' => ['nullable', 'string'],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $request->merge(
            [
                'reference' => ['nullable', 'string'],
                'page' => $request->query('page', 1),
                'per_page' => $request->query('per_page', 50),
                'sort' => $request->query('sort', 'id'),
            ]
        );
    }

}
