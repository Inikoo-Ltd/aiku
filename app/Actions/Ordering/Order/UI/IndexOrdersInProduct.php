<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UI;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Http\Resources\Ordering\OrdersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Order;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrdersInProduct extends OrgAction
{
    public function handle(Product $product, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('orders.reference', $value)
                    ->orWhereWith('orders.tracking_number', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Order::class);

        $query->whereIn('orders.id', function ($subQuery) use ($product) {
            $subQuery->select('transactions.order_id')
                ->from('transactions')
                ->where('transactions.model_type', 'Product')
                ->where('transactions.model_id', $product->id)
                ->whereNotNull('transactions.order_id')
                ->whereNull('transactions.deleted_at')
                ->distinct();
        });

        $query->leftJoin('customers', 'orders.customer_id', '=', 'customers.id');
        $query->leftJoin('customer_clients', 'orders.customer_client_id', '=', 'customer_clients.id');
        $query->leftJoin('currencies', 'orders.currency_id', '=', 'currencies.id');
        $query->leftJoin('organisations', 'orders.organisation_id', '=', 'organisations.id');
        $query->leftJoin('shops', 'orders.shop_id', '=', 'shops.id')->where('shops.state', ShopStateEnum::OPEN);

        return $query->defaultSort('-orders.date')
            ->select([
                'orders.id',
                'orders.slug',
                'orders.reference',
                'orders.date',
                'orders.submitted_at',
                'orders.dispatched_at',
                'orders.state',
                'orders.created_at',
                'orders.updated_at',
                'orders.net_amount',
                'orders.total_amount',
                'orders.payment_amount',
                'orders.pay_detailed_status',
                'orders.updated_by_customer_at',
                'orders.to_be_paid_by',
                'customers.name as customer_name',
                'customers.slug as customer_slug',
                'customers.is_vip as is_customer_vip',
                'customer_clients.name as client_name',
                'customer_clients.ulid as client_ulid',
                'currencies.code as currency_code',
                'currencies.id as currency_id',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
            ])
            ->leftJoin('order_stats', 'orders.id', 'order_stats.order_id')
            ->allowedSorts(['reference', 'date', 'net_amount', 'customer_name', 'pay_detailed_status', 'submitted_at'])
            ->withBetweenDates(['date'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Product $product, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($product, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');

                InertiaTable::updateQueryBuilderParameters($prefix);
            }

            $table->betweenDates(['date']);

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('order'), __('orders')])
                ->withEmptyState([
                    'title' => __('This product has not been ordered yet'),
                ]);

            $table->column(key: 'state', label: '', type: 'icon');
            $table->column(key: 'reference', label: __('Reference'), sortable: true);
            $table->column(key: 'date', label: __('Created date'), sortable: true, type: 'date');
            $table->column(key: 'customer_name', label: __('Customer'), sortable: true);
            $table->column(key: 'pay_detailed_status', label: __('Payment'), sortable: true);
            $table->column(key: 'net_amount', label: __('Net'), sortable: true, type: 'currency');
        };
    }

    public function jsonResponse(LengthAwarePaginator $orders): AnonymousResourceCollection
    {
        return OrdersResource::collection($orders);
    }
}
