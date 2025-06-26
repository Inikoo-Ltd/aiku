<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Fulfilment\Order;

use App\Actions\Api\Retina\Fulfilment\Resource\OrdersApiResource;
use App\Actions\RetinaApiAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Ordering\Order;
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
        $query = QueryBuilder::for(Order::class);

        $query->where('orders.customer_sales_channel_id', $customerSalesChannel->id);

        $query->leftJoin('customers', 'orders.customer_id', '=', 'customers.id');
        $query->leftJoin('customer_clients', 'orders.customer_client_id', '=', 'customer_clients.id');

        $query->leftJoin('model_has_payments', function ($join) {
            $join->on('orders.id', '=', 'model_has_payments.model_id')
                ->where('model_has_payments.model_type', '=', 'Order');
        })
            ->leftJoin('payments', 'model_has_payments.payment_id', '=', 'payments.id');

        $query->leftJoin('currencies', 'orders.currency_id', '=', 'currencies.id');
        $query->leftJoin('organisations', 'orders.organisation_id', '=', 'organisations.id');
        $query->leftJoin('shops', 'orders.shop_id', '=', 'shops.id');

        if (Arr::get($modelData, 'reference')) {
            $this->getReferenceSearch($query, Arr::get($modelData, 'reference'));
        }

        return $query->defaultSort('orders.id')
        ->select([
            'orders.id',
            'orders.reference',
            'orders.date',
            'orders.state',
            'orders.created_at',
            'orders.updated_at',
            'orders.slug',
            'orders.net_amount',
            'orders.total_amount',
            'customers.name as customer_name',
            'customers.slug as customer_slug',
            'customer_clients.name as client_name',
            'customer_clients.ulid as client_ulid',
            'payments.state as payment_state',
            'payments.status as payment_status',
            'currencies.code as currency_code',
            'currencies.id as currency_id',
            'shops.name as shop_name',
            'shops.slug as shop_slug',
            'organisations.name as organisation_name',
            'organisations.slug as organisation_slug',
        ])
            ->leftJoin('order_stats', 'orders.id', 'order_stats.order_id')
            ->allowedSorts(['id', 'reference', 'date', 'net_amount'])
            ->withBetweenDates(['date'])
            ->withPaginator(null, queryName: 'per_page')
            ->withQueryString();
    }

    public function getReferenceSearch($query, string $ref): QueryBuilder
    {
        return $query->where(function ($query) use ($ref) {
            $query->where('orders.reference', $ref);
        });
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($request);
        return $this->handle($this->customerSalesChannel, $this->validateAttributes());
    }


    public function jsonResponse(LengthAwarePaginator $orders): AnonymousResourceCollection
    {
        return OrdersApiResource::collection($orders);
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
