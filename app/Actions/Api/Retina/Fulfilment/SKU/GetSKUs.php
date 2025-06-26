<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Fulfilment\SKU;

use App\Actions\Api\Retina\Fulfilment\Resource\SKUsApiResource;
use App\Actions\RetinaApiAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Fulfilment\PalletStoredItem;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class GetSKUs extends RetinaApiAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): LengthAwarePaginator
    {

        $queryBuilder = QueryBuilder::for(PalletStoredItem::class)
        ->leftJoin('stored_items', 'pallet_stored_items.stored_item_id', '=', 'stored_items.id')
        ->leftJoin('pallets', 'pallet_stored_items.pallet_id', '=', 'pallets.id');

        $queryBuilder->where('pallets.fulfilment_customer_id', $customerSalesChannel->customer->fulfilmentCustomer->id);


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

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($request);
        return $this->handle($this->customerSalesChannel, $this->validateAttributes());
    }


    public function jsonResponse(LengthAwarePaginator $palletStoredItems): AnonymousResourceCollection
    {
        return SKUsApiResource::collection($palletStoredItems);
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
                'reference' => $request->query('reference', null),
                'page' => $request->query('page', 1),
                'per_page' => $request->query('per_page', 50),
                'sort' => $request->query('sort', 'id'),
            ]
        );
    }

}
