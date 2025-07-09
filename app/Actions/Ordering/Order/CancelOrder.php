<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\Dispatching\Picking\StoreNotPickPicking;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\CreditTransaction\CreditTransactionReasonEnum;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
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
            'state'  => OrderStateEnum::CANCELLED,
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
                'type' => CreditTransactionTypeEnum::MONEY_BACK,
                'reason' => CreditTransactionReasonEnum::MONEY_BACK,
                'notes' => "Order #{$order->reference} cancelled. Money returned as store credit.",
            ]);
        }

        $deliveryNotes = $order->deliveryNotes;
        foreach ($deliveryNotes as $deliveryNote) {
            if ($deliveryNote->pickings) {
                $deliveryNote->pickings()->delete();
                foreach ($deliveryNote->deliveryNoteItems as $item) {
                    StoreNotPickPicking::make()->action(
                        $item,
                        request()->user(),
                        [
                            'not_picked_reason' => PickingNotPickedReasonEnum::CANCELLED_BY_CUSTOMER,
                            'not_picked_note' => "Order #{$order->reference} cancelled. Picking not required.",
                            'quantity' => $item->quantity_required,
                        ]
                    );
                }
            }

            if ($deliveryNote->packings) {
                $deliveryNote->packings()->delete();
            }

            UpdateDeliveryNote::make()->action(
                $deliveryNote,
                [
                    'state' => DeliveryNoteStateEnum::CANCELLED,
                ],
                strict: false
            );
        }



        $this->orderHydrators($order);

        return $order;
    }

    public function afterValidator(Validator $validator)
    {
        $order = $this->order;
        if ($order->state === OrderStateEnum::CANCELLED) {
            $validator->errors()->add('messages', 'Order is already cancelled.');
        } elseif (!in_array($order->state, [OrderStateEnum::CREATING, OrderStateEnum::SUBMITTED, OrderStateEnum::IN_WAREHOUSE])) {
            $validator->errors()->add('messages', "Cannot cancel an order in '{$order->state->value}' state.");
        } elseif ($order->invoices()->count() > 0) {
            $validator->errors()->add('messages', 'Cannot cancel an order with invoices. Please delete the invoices first.');
        }

        $deliveryNotes = $order->deliveryNotes()->get();
        if ($deliveryNotes->count() > 0) {
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

    public function asController(Order $order, ActionRequest $request)
    {

        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);
        return $this->handle($order);
    }
}
