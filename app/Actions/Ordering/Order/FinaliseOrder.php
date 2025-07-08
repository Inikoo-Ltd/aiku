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

class FinaliseOrder extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;

    /**
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Throwable
     */
    public function handle(Order $order, $fromDeliveryNote = false): Order
    {
        GenerateOrderInvoice::make()->action($order);

        $data = [
            'state' => OrderStateEnum::FINALISED
        ];

        if (in_array($order->state, [OrderStateEnum::HANDLING, OrderStateEnum::PACKED]) || $fromDeliveryNote) {
            $order->transactions()->update([
                'state' => TransactionStateEnum::FINALISED
            ]);

            $data['finalised_at'] = now();

            $this->update($order, $data);

            $this->orderHydrators($order);

            return $order;
        }

        throw ValidationException::withMessages(['status' => 'You can not change the status to finalized']);
    }


    /**
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     */
    public function action(Order $order, $fromDeliveryNote = false): Order
    {
        $this->asAction = true;
        $this->initialisationFromShop($order->shop, []);

        return $this->handle($order, $fromDeliveryNote);
    }


}
