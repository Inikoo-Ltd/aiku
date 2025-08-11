<?php

/*
 * author Arya Permana - Kirin
 * created on 14-07-2025-17h-10m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Ordering\Order;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\Dispatching\DeliveryNote\CancelDeliveryNote;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\CreditTransaction\CreditTransactionReasonEnum;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class CancelOrderWithDeliveryNote extends OrgAction
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
        $this->update($order, $modelData);

        $transactions = $order->transactions;

        /** @var Transaction $transaction */
        foreach ($transactions as $transaction) {
            $transactionData = ['state' => TransactionStateEnum::CANCELLED];
            data_set($transactionData, 'quantity_cancelled', $transaction->quantity_ordered);

            $transaction->update($transactionData);
        }

        if ($order->payment_amount > 0) {
            StoreCreditTransaction::make()->action($order->customer, [
                'amount' => $order->payment_amount,
                'type' => CreditTransactionTypeEnum::MONEY_BACK,
                'reason' => CreditTransactionReasonEnum::MONEY_BACK,
                'notes' => "Order #{$order->reference} cancelled. Money returned as store credit.",
            ]);
        }

        $deliveryNotes = $order->deliveryNotes;
        foreach ($deliveryNotes as $deliveryNote) {
            CancelDeliveryNote::make()->action($deliveryNote);
        }



        $this->orderHydrators($order);

        return $order;
    }

    public function afterValidator(Validator $validator)
    {
        $order = $this->order;
        if ($order->state === OrderStateEnum::CANCELLED) {
            $validator->errors()->add('messages', 'Order is already cancelled.');
        }
    }

    public function action(Order $order): Order
    {
        $this->asAction = true;
        $this->order    = $order;
        $this->initialisationFromShop($order->shop, []);

        return $this->handle($order);
    }

    public function asController(Order $order, ActionRequest $request)
    {

        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);
        return $this->handle($order);
    }
}
