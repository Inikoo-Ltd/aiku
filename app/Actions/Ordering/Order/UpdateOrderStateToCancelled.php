<?php

/*
 * author Arya Permana - Kirin
 * created on 07-04-2025-11h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Ordering\Order;

use App\Actions\Accounting\Invoice\DeleteInvoice;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrderStateToCancelled extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;
    use WithOrderingEditAuthorisation;


    private Order $order;


    public function handle(Order $order): Order
    {
        $modelData = [
            'state'  => OrderStateEnum::CANCELLED,
        ];

        $date = now();

        if ($order->cancelled_at == null) {
            data_set($modelData, 'cancelled_at', $date);
        }

        $transactions = $order->transactions()->where('state', TransactionStateEnum::CREATING)->get();
        foreach ($transactions as $transaction) {
            $transactionData = ['state' => TransactionStateEnum::CANCELLED];
            data_set($transactionData, 'quantity_cancelled', $transaction->quantity_ordered);

            $transaction->update($transactionData);
        }

        $deliveryNotes = $order->deliveryNotes()->get();
        foreach ($deliveryNotes as $deliveryNote) {
            UpdateDeliveryNote::make()->action(
                $deliveryNote,
                [
                    'state' => DeliveryNoteStateEnum::CANCELLED,
                ],
                strict: false
            );
            $deliveryNote->state = OrderStateEnum::CANCELLED;
            $deliveryNote->save();
        }

        $invoices = $order->invoices()->get();
        foreach ($invoices as $invoice) {
            DeleteInvoice::make()->action(
                $invoice,
                [
                    'deleted_note' => 'Order cancelled',
                ]
            );
        }

        $this->update($order, $modelData);
        $this->orderHydrators($order);

        return $order;
    }

    public function afterValidator(Validator $validator)
    {
        $order = $this->order;
        if ($order && $order->state === OrderStateEnum::CANCELLED) {
            $validator->errors()->add('messages', 'Order is already cancelled.');
        } elseif ($order && !in_array($order->state, [OrderStateEnum::CREATING, OrderStateEnum::SUBMITTED, OrderStateEnum::IN_WAREHOUSE])) {
            $validator->errors()->add('messages', "Cannot cancel an order in '{$order->state->value}' state.");
        }
    }

    public function action(Order $order): Order
    {
        $this->asAction = true;
        $this->scope    = $order->shop;
        $this->order    = $order;
        $this->initialisationFromShop($order->shop, []);

        return $this->handle($order);
    }

    public function asController(Order $order, ActionRequest $request)
    {
        $this->order = $order;
        $this->scope = $order->shop;
        $this->initialisationFromShop($order->shop, $request);
        return $this->handle($order);
    }
}
