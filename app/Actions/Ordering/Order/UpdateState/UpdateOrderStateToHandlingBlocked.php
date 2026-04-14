<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Apr 2026 11:27:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UpdateState;

use App\Actions\Ordering\Order\HasOrderHydrators;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;

class UpdateOrderStateToHandlingBlocked extends OrgAction
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
            OrderStateEnum::HANDLING_BLOCKED,
            null,
            'handling_blocked_at'
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
