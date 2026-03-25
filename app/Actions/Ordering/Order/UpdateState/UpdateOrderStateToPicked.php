<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UpdateState;

use App\Actions\Ordering\Order\HasOrderHydrators;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;

class UpdateOrderStateToPicked extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;
    use HasOrderStateUpdates;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Order $order, DeliveryNote $deliveryNote): Order
    {
        return $this->updateOrderState(
            $order,
            $deliveryNote,
            OrderStateEnum::PICKED,
            TransactionStateEnum::PICKED,
            'picked_at'
        );
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function action(Order $order, DeliveryNote $deliveryNote): Order
    {
        $this->asAction = true;
        $this->initialisationFromShop($order->shop, []);

        return $this->handle($order, $deliveryNote);
    }


}
