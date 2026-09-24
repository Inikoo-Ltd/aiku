<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Sep 2026 Malaga, Spain
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

/**
 * An invoiced order whose note was unpacked and packed again goes back to finalised as it was.
 * Nothing is recalculated: the amounts are the invoice's, and FinaliseOrder would refuse because
 * the invoice already exists (HELP-3153).
 */
class UpdateOrderStateBackToFinalised extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Order $order): Order
    {
        $oldState = $order->state;

        if (!in_array($oldState, [OrderStateEnum::PACKING, OrderStateEnum::PACKED])) {
            throw ValidationException::withMessages(['status' => 'Error, order state is '.$oldState->value]);
        }

        $order->transactions()->where('model_type', 'Product')
            ->where('is_follow_on', false)
            ->where('state', '!=', TransactionStateEnum::CANCELLED)
            ->update([
            'state' => TransactionStateEnum::FINALISED
        ]);

        $this->update($order, ['state' => OrderStateEnum::FINALISED]);

        $this->orderHydrators($order);
        $this->orderHandlingHydrators($order, $oldState);
        $this->orderHandlingHydrators($order, OrderStateEnum::FINALISED);

        return $order;
    }

    public function action(Order $order): Order
    {
        $this->asAction = true;
        $this->initialisationFromShop($order->shop, []);

        return $this->handle($order);
    }
}
