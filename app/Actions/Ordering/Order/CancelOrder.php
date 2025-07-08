<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
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

        $deliveryNotes = $order->deliveryNotes()->get();
        foreach ($deliveryNotes as $deliveryNote) {
            //todo create new action CancelDeliveryNote
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
        }

        if ($order->invoices()->count() > 0) {
            $validator->errors()->add('messages', 'Cannot cancel an order with invoices. Please delete the invoices first.');
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
