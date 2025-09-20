<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Dispatching\DeliveryNote\CancelDeliveryNote;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\CreditTransaction\CreditTransactionReasonEnum;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class CancelOrder extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;
    use WithOrderingEditAuthorisation;


    private Order $order;


    public function handle(Order $order): Order
    {
        $modelData = [
            'state' => OrderStateEnum::CANCELLED,
        ];

        $date = now();

        if ($order->cancelled_at == null) {
            data_set($modelData, 'cancelled_at', $date);
        }
        $this->update($order, $modelData);

        $transactions = $order->transactions()->where('state', TransactionStateEnum::CREATING)->get();

        /** @var Transaction $transaction */
        foreach ($transactions as $transaction) {
            $transactionData = ['state' => TransactionStateEnum::CANCELLED];
            data_set($transactionData, 'quantity_cancelled', $transaction->quantity_ordered);

            $transaction->update($transactionData);
        }

        if ($order->payment_amount > 0) {
            StoreCreditTransaction::make()->action($order->customer, [
                'amount' => $order->payment_amount,
                'type'   => CreditTransactionTypeEnum::MONEY_BACK,
                'reason' => CreditTransactionReasonEnum::MONEY_BACK,
                'notes'  => "Order #$order->reference cancelled. Money returned as store credit.",
            ]);


            $paymentAccountShop = PaymentAccountShop::where('shop_id', $order->shop_id)->where('type', 'account')->where('state', 'active')->first();

            $paymentData = [
                'reference'               => 'cu-'.$order->customer->id.'-return-bal-'.Str::random(10),
                'amount'                  => -$order->payment_amount,
                'status'                  => PaymentStatusEnum::SUCCESS,
                'payment_account_shop_id' => $paymentAccountShop->id,
                'state'                   => PaymentStateEnum::COMPLETED,
                'type'                    => PaymentTypeEnum::REFUND
            ];



            $payment     = StorePayment::make()->action($order->customer, $paymentAccountShop->paymentAccount, $paymentData);

            AttachPaymentToOrder::make()->action($order, $payment, [
                'amount' => $payment->amount
            ]);


        }

        $deliveryNotes = $order->deliveryNotes;
        foreach ($deliveryNotes as $deliveryNote) {
            CancelDeliveryNote::make()->action($deliveryNote, true);
        }


        $this->orderHydrators($order);

        return $order;
    }

    public function afterValidator(Validator $validator): void
    {
        $order = $this->order;
        if ($order->state === OrderStateEnum::CANCELLED) {
            $validator->errors()->add('messages', 'Order is already cancelled.');
        } elseif (in_array($order->state, [OrderStateEnum::DISPATCHED, OrderStateEnum::FINALISED])) {
            $validator->errors()->add('messages', "Cannot cancel an order in '{$order->state->value}' state.");
        } elseif ($order->invoices()->count() > 0) {
            $validator->errors()->add('messages', 'Cannot cancel an order with invoices. Please delete the invoices first.');
        }

        $deliveryNotes = $order->deliveryNotes()->get();
        if ($deliveryNotes->count() > 0) {
            /** @var \App\Models\Dispatching\DeliveryNote $deliveryNote */
            foreach ($deliveryNotes as $deliveryNote) {
                if ($deliveryNote->state === DeliveryNoteStateEnum::DISPATCHED) {
                    $validator->errors()->add('messages', 'Cannot cancel an order with dispatched delivery notes. Please cancel the delivery notes first.');
                }
            }
        }
    }

    public function action(Order $order): Order
    {
        $this->asAction = true;
        $this->order    = $order;
        $this->initialisationFromShop($order->shop, []);

        return $this->handle($order);
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order);
    }
}
