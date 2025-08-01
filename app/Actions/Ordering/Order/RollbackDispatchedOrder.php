<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:11 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

class RollbackDispatchedOrder extends OrgAction
{
    public function handle(Order $order): void
    {
        if ($order->deliveryNotes) {
            foreach ($order->deliveryNotes as $deliveryNote) {
                if ($deliveryNote->state == DeliveryNoteStateEnum::DISPATCHED) {
                    UpdateDeliveryNote::make()->action($deliveryNote, [
                        'state' => DeliveryNoteStateEnum::FINALISED,
                        'dispatched_at' => null,
                    ]);
                }
            }
        }

        UpdateOrder::make()->action($order, [
            'state' => OrderStateEnum::FINALISED,

        ]);
    }

    public function asController(Order $order, ActionRequest $request): void
    {
        $this->initialisationFromShop($order->shop, $request);

        $this->handle($order);
    }
}
