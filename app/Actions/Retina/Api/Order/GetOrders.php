<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Api\Order;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Http\Resources\Sales\OrderResource;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Carbon\Carbon;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetOrders
{
    use AsAction;
    use WithAttributes;

    public function handle(Customer $customer, array $modelData): LengthAwarePaginator
    {
        $query = QueryBuilder::for(Order::class);

        $query->where('orders.customer_id', $customer->id);

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

        $state = Arr::get($modelData, 'state');

        if ($state === OrderStateEnum::CREATING->value) {
            $query->where('orders.state', OrderStateEnum::CREATING);
        } elseif ($state === OrderStateEnum::SUBMITTED->value) {
            $query->where('orders.state', OrderStateEnum::SUBMITTED);
        } elseif ($state === OrderStateEnum::HANDLING->value) {
            $query->where('orders.state', OrderStateEnum::HANDLING);
        } elseif ($state === OrderStateEnum::HANDLING_BLOCKED->value) {
            $query->where('orders.state', OrderStateEnum::HANDLING_BLOCKED);
        } elseif ($state === OrderStateEnum::PACKED->value) {
            $query->where('orders.state', OrderStateEnum::PACKED);
        } elseif ($state === OrderStateEnum::FINALISED->value) {
            $query->where('orders.state', OrderStateEnum::FINALISED);
        } elseif ($state === OrderStateEnum::DISPATCHED->value) {
            $query->where('orders.state', OrderStateEnum::DISPATCHED);
        } elseif ($state === OrderStateEnum::CANCELLED->value) {
            $query->where('orders.state', OrderStateEnum::CANCELLED);
        } elseif ($state === 'dispatched_today') {
            $query->where('orders.state', OrderStateEnum::DISPATCHED)
            ->whereDate('dispatched_at', Carbon::today());
        }

        if (Arr::get($modelData, 'search')) {
            $query->where(function ($query) use ($modelData) {
                $query->where('orders.reference', 'like', '%' . $modelData['search'] . '%');
            });
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

    public function asController(ActionRequest $request): LengthAwarePaginator
    {

        $customer = $request->user();
        $this->fillFromRequest($request);
        return $this->handle($customer, $this->validateAttributes());
    }


    public function jsonResponse(LengthAwarePaginator $orders): AnonymousResourceCollection
    {
        return OrderResource::collection($orders);
    }

    public function rules(): array
    {
        return [
            'state' => ['nullable', Rule::enum(OrderStateEnum::class)],
            'search' => ['nullable', 'string'],
            'page' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer'],
            'sort' => ['nullable', 'string'],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $request->merge(
            [
                'search' => $request->query('search', null),
                'state' => $request->query('state', null),
                'page' => $request->query('page', 1),
                'per_page' => $request->query('per_page', 50),
                'sort' => $request->query('sort', 'id'),
            ]
        );
    }

}
