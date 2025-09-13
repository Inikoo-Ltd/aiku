<?php

/*
 * author Arya Permana - Kirin
 * created on 07-05-2025-15h-53m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Accounting\Payment;

use App\Actions\Ordering\Order\SubmitOrder;
use App\Actions\Retina\Dropshipping\Orders\WithBasketStateWarning;
use App\Actions\Retina\Dropshipping\Orders\WithRetinaOrderPlacedRedirection;
use App\Actions\RetinaAction;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

class PlaceOrderPayByBank extends RetinaAction
{
    use WithBasketStateWarning;
    use WithRetinaOrderPlacedRedirection;

    public function handle(Customer $customer): array
    {

        $order = Order::find($customer->current_order_in_basket_id);
        if (!$order) {
            return [
                'success' => false,
                'reason'  => 'Order not found',
                'order'   => null,
            ];
        }

        $order = SubmitOrder::run($order);
        return [
            'success' => true,
            'reason'  => 'Order submitted successfully',
            'order'   => $order,
        ];

    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);
        return $this->handle($this->customer);
    }

}
