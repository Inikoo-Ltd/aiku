<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox\OutOfStockInOrder;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Models\Catalogue\Product;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Customers with a submitted order holding a product that has run out since the last run,
 * one row per customer. Shared by the email and the WhatsApp channel so both tell the same
 * customers about the same products.
 */
class GetOutOfStockInOrderCustomersQuery
{
    use AsObject;

    public function handle(int $shopId, ?string $stockChangedAfter): Builder
    {
        $productClass = class_basename(Product::class);

        $baseQuery = DB::table('customers');
        $baseQuery->where('customers.shop_id', $shopId);
        $baseQuery->whereNull('customers.deleted_at');

        $baseQuery->join('orders', function ($join) {
            $join->on('customers.id', '=', 'orders.customer_id');
            $join->whereIn('orders.state', [OrderStateEnum::SUBMITTED->value]);
            $join->whereIn('orders.status', [OrderStatusEnum::PROCESSING->value]);
            $join->whereNull('orders.deleted_at');
        });

        $baseQuery->join('transactions', function ($join) {
            $join->on('orders.id', '=', 'transactions.order_id');
            $join->whereNull('transactions.deleted_at');
        });

        $baseQuery->join('products', function ($join) use ($stockChangedAfter, $productClass) {
            $join->on('transactions.model_id', '=', 'products.id');
            $join->where('transactions.model_type', $productClass);
            $join->where('products.is_for_sale', true);
            $join->whereIn('products.state', [
                ProductStateEnum::ACTIVE->value,
                ProductStateEnum::DISCONTINUING->value,
            ]);
            $join->where('products.available_quantity', '<=', 0);

            if ($stockChangedAfter) {
                $join->where('products.available_quantity_updated_at', '>', $stockChangedAfter);
            }

            $join->whereNull('products.deleted_at');
        });

        $baseQuery->select(
            'customers.id',
            'customers.email',
            DB::raw('STRING_AGG(products.id::TEXT, \',\' ORDER BY products.id) AS product_ids'),
            DB::raw('STRING_AGG(DISTINCT orders.id::TEXT, \',\') AS order_ids')
        );
        $baseQuery->groupBy('customers.id');
        $baseQuery->orderBy('customers.id');

        return $baseQuery;
    }
}
