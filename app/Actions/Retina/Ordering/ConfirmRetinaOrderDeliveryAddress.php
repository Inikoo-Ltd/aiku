<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 11 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ordering;

use App\Actions\Ordering\Order\UI\GetEarlierDeliveryAddressWarning;
use App\Actions\RetinaAction;
use App\Models\Ordering\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

/** The customer says the order's delivery address is right, so the earlier-address note stops showing for it */
class ConfirmRetinaOrderDeliveryAddress extends RetinaAction
{
    public function handle(Order $order): Order
    {
        $order->update([
            'data' => array_merge($order->data ?? [], [
                'delivery_address_confirmed' => GetEarlierDeliveryAddressWarning::make()->addressKey($order->deliveryAddress),
            ]),
        ]);

        return $order;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('order')->customer_id == $this->customer->id;
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisation($request);

        return $this->handle($order);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
