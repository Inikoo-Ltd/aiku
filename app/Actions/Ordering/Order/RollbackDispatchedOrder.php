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
    public function handle(Order $order)
    {
        if($order->invoices) {
            $order->invoices()->delete();
        }

        if($order->deliveryNotes) {
            foreach($order->deliveryNotes as $deliveryNote) {
                UpdateDeliveryNote::make()->action($deliveryNote,  [
                    'state' => DeliveryNoteStateEnum::PACKED
                ]);
            }
        }

        UpdateOrder::make()->action($order, [
            'state' => OrderStateEnum::PACKED
        ]);
    }

    public function asController(Order $order, ActionRequest $request)
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order);
    }
}
