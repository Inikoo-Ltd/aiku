<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Fulfilment\Order;

use App\Actions\RetinaApiAction;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Http\Resources\Fulfilment\PalletReturnsResource;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Fulfilment\PalletReturn;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetOrders extends RetinaApiAction
{
    use AsAction;
    use WithAttributes;

    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): LengthAwarePaginator
    {
        $query = QueryBuilder::for(PalletReturn::class);

        if (Arr::get($modelData, 'reference')) {
            $this->getReferenceSearch($query, Arr::get($modelData, 'reference'));
        }


        $query->where('pallet_returns.type', PalletReturnTypeEnum::STORED_ITEM);
        $query->where('pallet_returns.platform_id', $customerSalesChannel->platform->id);
        $query->where('pallet_returns.customer_sales_channel_id', $customerSalesChannel->id);

        $query->where('pallet_returns.fulfilment_customer_id', $customerSalesChannel->customer->fulfilmentCustomer->id);

        $query->leftJoin('currencies', 'pallet_returns.currency_id', '=', 'currencies.id');
        $query->leftJoin('pallet_return_stats', 'pallet_returns.id', '=', 'pallet_return_stats.pallet_return_id');


        $query->select(
            'pallet_returns.id',
            'pallet_returns.slug',
            'pallet_returns.reference',
            'pallet_returns.state',
            'pallet_returns.type',
            'pallet_returns.customer_reference',
            'pallet_return_stats.number_pallets as number_pallets',
            'pallet_return_stats.number_services as number_services',
            'pallet_return_stats.number_physical_goods as number_physical_goods',
            'pallet_returns.date',
            'pallet_returns.total_amount',
            'pallet_returns.created_at',
            'currencies.code as currency_code',
        );

        return $query->defaultSort('id')
            ->allowedSorts(['id'])
            ->withBetweenDates(['date'])
            ->withPaginator(null, queryName: 'per_page')
            ->withQueryString();
    }

    public function getReferenceSearch($query, string $ref): QueryBuilder
    {
        return $query->where(function ($query) use ($ref) {
            $query->where('pallet_returns.reference', $ref);
        });
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($request);
        return $this->handle($this->customerSalesChannel, $this->validateAttributes());
    }


    public function jsonResponse(LengthAwarePaginator $palletReturns): AnonymousResourceCollection
    {
        return PalletReturnsResource::collection($palletReturns);
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
