<?php

namespace App\Audits\Transformer;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;

class OrderSubmitSummaryTransformer
{
    public static function execute(Order $order, array $data): array
    {
        $oldState = Arr::get($data, 'old_values.state');
        $newState = Arr::get($data, 'new_values.state');

        $oldStateValue = $oldState instanceof \UnitEnum ? $oldState->value : $oldState;
        $newStateValue = $newState instanceof \UnitEnum ? $newState->value : $newState;

        if (Arr::get($data, 'event') !== 'updated'
            || $oldStateValue !== OrderStateEnum::CREATING->value
            || $newStateValue !== OrderStateEnum::SUBMITTED->value) {
            return $data;
        }

        $totalQtyItems = (float) $order->itemTransactions()->sum('quantity_ordered');
        $toBePaidBy = $order->to_be_paid_by instanceof \UnitEnum ? $order->to_be_paid_by->value : $order->to_be_paid_by;

        $data['old_values']['products_bought'] = null;
        $data['new_values']['products_bought'] = $order->number_item_transactions;

        $data['old_values']['total_qty_items'] = null;
        $data['new_values']['total_qty_items'] = $totalQtyItems;

        $data['old_values']['shipping_amount'] = null;
        $data['new_values']['shipping_amount'] = $order->shipping_amount;

        $data['old_values']['discount_amount'] = null;
        $data['new_values']['discount_amount'] = $order->amount_off;

        $data['old_values']['to_be_paid_by'] = null;
        $data['new_values']['to_be_paid_by'] = $toBePaidBy;

        return $data;
    }
}
