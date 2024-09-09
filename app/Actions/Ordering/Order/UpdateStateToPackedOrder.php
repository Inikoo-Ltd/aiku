<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UpdateStateToPackedOrder extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Order $order): Order
    {
        $data = [
            'state' => OrderStateEnum::PACKED
        ];

        if (in_array($order->state, [\App\Enums\Ordering\Order\OrderStateEnum::HANDLING, \App\Enums\Ordering\Order\OrderStateEnum::FINALISED])) {
            $order->transactions()->update([
                'state' => TransactionStateEnum::PACKED,
            ]);

            // $data[$order->state->value . '_at'] = null;
            $data['packed_at']                  = now();

            $this->update($order, $data);

            $this->orderHydrators($order);

            return $order;
        }

        throw ValidationException::withMessages(['status' => 'You can not change the status to submitted']);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function action(Order $order): Order
    {
        return $this->handle($order);
    }

    public function asController(Order $order, ActionRequest $request)
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);
        return $this->handle($order);
    }
}
