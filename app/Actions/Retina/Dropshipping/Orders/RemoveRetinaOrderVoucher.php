<?php

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Ordering\Order\RemoveVoucherFromOrder;
use App\Actions\RetinaAction;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

class RemoveRetinaOrderVoucher extends RetinaAction
{
    public function handle(Order $order): void
    {
        RemoveVoucherFromOrder::run($order);
    }

    public function authorize(ActionRequest $request): bool
    {
        $order = $request->route('order');

        return $order->customer_id == $this->customer->id;
    }


    public function asController(Order $order, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($order);
    }
}
