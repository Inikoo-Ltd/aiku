<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Actions\Ordering\Transaction\SyncBasketLinesWithProductStock;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

trait WithBasketStockIssues
{
    /**
     * Lines ordering more than the warehouse shows in stock. Orders are never blocked on stock.
     * A short line is sent as far as possible and the rest credited; an out of stock line has
     * been zeroed by SyncBasketLinesWithProductStock and carries the quantity it will get back.
     *
     * @return array{low_stock: list<array{transaction_id:int, product_id:int, code:string, name:string, quantity_ordered:float, held_quantity:float, available_quantity:float}>, out_of_stock: list<array{transaction_id:int, product_id:int, code:string, name:string, quantity_ordered:float, held_quantity:float, available_quantity:float}>}
     */
    protected function getBasketStockIssues(Order $order): array
    {
        $lines = DB::table('transactions')
            ->join('assets', 'transactions.asset_id', '=', 'assets.id')
            ->join('products', 'assets.model_id', '=', 'products.id')
            ->where('transactions.order_id', $order->id)
            ->where('transactions.model_type', 'Product')
            ->where('assets.model_type', 'Product')
            ->where('products.is_on_demand', false)
            ->whereNull('transactions.deleted_at')
            ->where(function ($query) {
                $query->whereColumn('products.available_quantity', '<', 'transactions.quantity_ordered')
                    ->orWhereNotNull('transactions.data->'.SyncBasketLinesWithProductStock::HELD_QUANTITY_KEY);
            })
            ->orderBy('transactions.id')
            ->get([
                'transactions.id as transaction_id',
                'products.id as product_id',
                'products.code',
                'products.name',
                'transactions.quantity_ordered',
                'transactions.data',
                'products.available_quantity',
            ])
            ->map(fn ($line) => [
                'transaction_id'     => $line->transaction_id,
                'product_id'         => $line->product_id,
                'code'               => $line->code,
                'name'               => $line->name,
                'quantity_ordered'   => (float) $line->quantity_ordered,
                'held_quantity'      => (float) Arr::get(json_decode($line->data ?? '{}', true), SyncBasketLinesWithProductStock::HELD_QUANTITY_KEY, 0),
                'available_quantity' => max(0, (float) $line->available_quantity),
            ]);

        return [
            'low_stock'    => $lines->where('available_quantity', '>', 0)->values()->all(),
            'out_of_stock' => $lines->where('available_quantity', '<=', 0)->values()->all(),
        ];
    }
}
