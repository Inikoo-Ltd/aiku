<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Feb 2026 13:16:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UpdateState;

use App\Actions\Ordering\Order\HasOrderHydrators;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrderStateToPacking extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Order $order, bool $fromDeliveryNote = false): Order
    {
        $oldState = $order->state;
        $data     = [
            'state' => OrderStateEnum::PACKING
        ];

        if (in_array($order->state, [
                OrderStateEnum::PICKED,
            ])
            || $fromDeliveryNote) {
            foreach ($order->transactions()->where('model_type', 'Product')
                ->where('transactions.state', TransactionStateEnum::PICKED)->get() as $transaction) {
                $transaction->update(
                    [
                        'state'           => TransactionStateEnum::PACKING,
                    ]
                );
            }


            $data['packing_at'] = now();

            $this->update($order, $data);



            $this->orderHydrators($order);
            $this->orderHandlingHydrators($order, $oldState);
            $this->orderHandlingHydrators($order, OrderStateEnum::PACKING);

            return $order;
        }

        throw ValidationException::withMessages(['status' => 'Error, order state is '.$order->state->value]);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function action(Order $order, bool $fromDeliveryNote): Order
    {
        $this->asAction = true;
        $this->initialisationFromShop($order->shop, []);

        return $this->handle($order, $fromDeliveryNote);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order);
    }
}
