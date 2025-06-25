<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Dropshipping\Transaction;

use App\Actions\RetinaApiAction;
use App\Http\Resources\Api\TransactionsResource;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetTransactions extends RetinaApiAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Order $order, array $modelData): LengthAwarePaginator
    {
        $query = QueryBuilder::for(Transaction::class);

        $query->where('transactions.order_id', $order->id);

        if (Arr::get($modelData, 'search')) {
            $query->where(function ($query) use ($modelData) {
                $query->where('orders.reference', 'like', '%' . $modelData['search'] . '%');
            });
        }

        $query->whereIn('transactions.model_type', ['Product', 'Service']);

        $query->leftjoin('assets', 'transactions.asset_id', '=', 'assets.id');
        $query->leftjoin('products', 'assets.model_id', '=', 'products.id');
        $query->leftjoin('orders', 'transactions.order_id', '=', 'orders.id');
        $query->leftjoin('currencies', 'orders.currency_id', '=', 'currencies.id');


        return $query->defaultSort('transactions.id')
            ->select([
                'transactions.id',
                'transactions.state',
                'transactions.status',
                'transactions.quantity_ordered',
                'transactions.quantity_bonus',
                'transactions.quantity_dispatched',
                'transactions.quantity_fail',
                'transactions.quantity_cancelled',
                'transactions.gross_amount',
                'transactions.net_amount',
                'transactions.created_at',
                'assets.code as asset_code',
                'assets.name as asset_name',
                'assets.type as asset_type',
                'products.price as price',
                'products.slug as product_slug',
                'currencies.code as currency_code',
                'orders.id as order_id',
            ])
            ->leftJoin('order_stats', 'orders.id', 'order_stats.order_id')
            ->allowedSorts(['id', 'reference', 'date', 'net_amount'])
            ->withBetweenDates(['date'])
            ->withPaginator(null, queryName: 'per_page')
            ->withQueryString();
    }

    public function asController(Order $order, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromDropshipping($request);
        return $this->handle($order, $this->validatedData);
    }


    public function jsonResponse(LengthAwarePaginator $orders): AnonymousResourceCollection
    {
        return TransactionsResource::collection($orders);
    }

    public function rules(): array
    {
        return [
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
                'page' => $request->query('page', 1),
                'per_page' => $request->query('per_page', 50),
                'sort' => $request->query('sort', 'id'),
            ]
        );
    }

}
