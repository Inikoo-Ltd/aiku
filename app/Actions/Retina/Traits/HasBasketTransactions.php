<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Mar 2026 11:18:03 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Traits;

use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;

trait HasBasketTransactions
{
    private function getBasketTransactions(Customer $customer): array
    {
        if (! $customer->current_order_in_basket_id) {
            return [];
        }

        $order = Order::find($customer->current_order_in_basket_id);
        if (! $order) {
            return [];
        }

        // Get transactions the same way as ShowRetinaEcomBasket
        $transactions = $order->transactions()
            ->whereIn('model_type', ['Product', 'Service'])
            ->with(['asset.product'])
            ->get();

        $basketTransactions = [];
        /** @var Transaction $transaction */
        foreach ($transactions as $transaction) {
            // Use product ID as a key to match with favorites data (products.id)
            $productId = $transaction->asset?->product?->id;

            if ($productId) {
                $basketTransactions[$productId] = [
                    'id' => $transaction->id,
                    'quantity_ordered' => (int) $transaction->quantity_ordered,
                    'asset_id' => $transaction->asset_id,
                    'product_id' => $productId,
                ];
            }
        }

        return $basketTransactions;
    }
}
