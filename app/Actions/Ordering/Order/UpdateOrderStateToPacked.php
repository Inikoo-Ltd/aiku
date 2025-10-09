<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
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

class UpdateOrderStateToPacked extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Order $order, bool $fromDeliveryNote = false): Order
    {
        $data = [
            'state' => OrderStateEnum::PACKED
        ];

        if (in_array($order->state, [
            OrderStateEnum::HANDLING,
            OrderStateEnum::FINALISED,
            OrderStateEnum::IN_WAREHOUSE,
        ]) || $fromDeliveryNote) {
            $order->transactions()->update([
                'state' => TransactionStateEnum::PACKED,
            ]);

            $data['packed_at']                  = now();

            $this->update($order, $data);

            $this->orderHydrators($order);

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
