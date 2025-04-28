<?php

/*
 * author Arya Permana - Kirin
 * created on 07-04-2025-11h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\HasOrderingAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrderStateToCancelled extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;
    use HasOrderingAuthorisation;


    private Order $order;

    public function __construct()
    {
        $this->authorisationType = 'update';
    }

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

        $this->update($order, $modelData);
        $this->orderHydrators($order);

        return $order;
    }


    public function afterValidator(Validator $validator): void
    {
        if ($this->order->state == OrderStateEnum::CANCELLED) {
            $validator->errors()->add('state', __('Order has been cancelled'));
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
