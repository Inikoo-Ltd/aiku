<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 05 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The GA4 "purchase" ecommerce object of an order, shared by the browser push after checkout
 * and the server-side Measurement Protocol send, so both report the same transaction.
 */
class GetOrderPurchaseEcommerceData
{
    use AsAction;

    /**
     * @return array{transaction_id: int, value: float, tax: float, shipping: float, currency: string, items: array<int, array{item_id: string, item_name: string, index: int, price: float, quantity: float}>}
     */
    public function handle(Order $order): array
    {
        $transactionsData = DB::table('transactions')
            ->select('webpages.slug', 'products.name', 'products.price', 'transactions.quantity_ordered')
            ->where('transactions.order_id', $order->id)
            ->where('transactions.deleted_at', null)
            ->where('transactions.model_type', 'Product')
            ->whereNotNull('webpages.slug')
            ->leftJoin('products', 'transactions.model_id', '=', 'products.id')
            ->leftJoin('webpages', 'products.webpage_id', '=', 'webpages.id')
            ->get();

        $items = [];
        $index = 1;
        foreach ($transactionsData as $transactionData) {
            $items[] = [
                'item_id'   => 'webpage-'.$transactionData->slug,
                'item_name' => $transactionData->name,
                'index'     => $index++,
                'price'     => (float)$transactionData->price,
                'quantity'  => (float)$transactionData->quantity_ordered,
            ];
        }

        return [
            'transaction_id' => $order->id,
            'value'          => (float)$order->total_amount,
            'tax'            => (float)$order->tax_amount,
            'shipping'       => (float)$order->shipping_amount,
            'currency'       => $order->shop->currency->code,
            'items'          => $items,
        ];
    }
}
