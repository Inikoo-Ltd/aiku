<?php

/*
 * author Arya Permana - Kirin
 * created on 15-04-2025-16h-38m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydrateOrders;
use App\Actions\Ordering\Order\SubmitOrder;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SubmitRetinaOrder extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Order $order): Order
    {
        $order = SubmitOrder::run($order);


        if (!$order->customer_id && !$order->platform) {
            return $order;
        }

        $customerSalesChannel = CustomerSalesChannel::where('customer_id', $order->customer_id)
        ->where('platform_id', $order->platform_id)
        ->first();

        CustomerSalesChannelsHydrateOrders::dispatch($customerSalesChannel);

        return $order;
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisation($request);

        return $this->handle($order, $this->validatedData);
    }
}
