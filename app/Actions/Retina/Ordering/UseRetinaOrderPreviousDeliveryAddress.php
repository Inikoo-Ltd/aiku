<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 11 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ordering;

use App\Actions\Ordering\Order\UI\GetEarlierDeliveryAddressWarning;
use App\Actions\Ordering\Order\UpdateOrderDeliveryAddress;
use App\Actions\RetinaAction;
use App\Models\Ordering\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

/** One click back to the address the customer's last order was delivered to, rebuilt here rather than sent by the page */
class UseRetinaOrderPreviousDeliveryAddress extends RetinaAction
{
    public function handle(Order $order): Order
    {
        $previousAddress = GetEarlierDeliveryAddressWarning::make()->previousDeliveredAddress($order);

        if (!$previousAddress) {
            return $order;
        }

        return UpdateOrderDeliveryAddress::make()->action($order, [
            'address' => $previousAddress->only([
                'address_line_1', 'address_line_2', 'sorting_code', 'postal_code', 'dependent_locality',
                'locality', 'administrative_area', 'country_code', 'country_id',
            ]),
        ]);
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
