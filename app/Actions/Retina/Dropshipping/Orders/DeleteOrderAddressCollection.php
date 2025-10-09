<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Ordering\Order\UpdateOrder;
use App\Actions\RetinaAction;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

class DeleteOrderAddressCollection extends RetinaAction
{
    public function handle(Order $order): Order
    {
        return UpdateOrder::run($order, [
            'collection_address_id' => null
        ]);
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisation($request);

        return $this->handle($order);
    }
}
