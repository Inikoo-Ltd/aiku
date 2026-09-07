<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Mar 2026 00:28:18 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UpdateState;

use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\GenerateInvoiceFromOrder;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use Illuminate\Validation\ValidationException;

trait HasOrderStateUpdates
{
    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function updateOrderState(Order $order, DeliveryNote $deliveryNote, OrderStateEnum $newState, ?TransactionStateEnum $newTransactionState, string $timestampField): Order
    {
        $oldState = $order->state;
        $data     = [
            'state' => $newState
        ];

        if (!in_array($order->state, [
            OrderStateEnum::CANCELLED,
            OrderStateEnum::DISPATCHED,
            OrderStateEnum::CREATING,
            OrderStateEnum::SUBMITTED,
        ])) {
            /** Until the note is picked a short or missing line is not final - it is waiting, not
             * lost - so the order keeps the amounts the customer submitted. Only from picked on
             * are the amounts rewritten to what was actually taken, which is what gets invoiced. */
            $picksAreFinal = $newState->isPickedOrLater();

            // INI-1811: Guard, is follow on products must always be 0
            foreach ($order->transactions()->where('model_type', 'Product')->where('is_follow_on', false)->get() as $transaction) {
                $packedData = GenerateInvoiceFromOrder::make()->recalculateTransactionTotals($transaction, $deliveryNote);

                $transactionData = [
                    'status'          => TransactionStatusEnum::PROCESSING,
                    'quantity_picked' => $packedData['quantity'],
                ];
                if ($picksAreFinal) {
                    $transactionData['gross_amount']   = $packedData['gross_amount'];
                    $transactionData['net_amount']     = $packedData['net_amount'];
                    $transactionData['org_net_amount'] = $packedData['org_net_amount'];
                    $transactionData['grp_net_amount'] = $packedData['grp_net_amount'];
                }
                if ($newTransactionState !== null) {
                    $transactionData['state'] = $newTransactionState;
                }

                $transaction->update($transactionData);
            }

            $data[$timestampField] = now();

            $this->update($order, $data);

            CalculateOrderTotalAmounts::run($order);
            $order->refresh();

            $this->orderHydrators($order);
            $this->orderHandlingHydrators($order, $oldState);
            $this->orderHandlingHydrators($order, $newState);

            return $order;
        }

        throw ValidationException::withMessages(['status' => 'Error, order state is '.$order->state->value]);
    }
}
