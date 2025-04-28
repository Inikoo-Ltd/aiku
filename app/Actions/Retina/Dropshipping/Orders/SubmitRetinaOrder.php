<?php

/*
 * author Arya Permana - Kirin
 * created on 15-04-2025-16h-38m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Dropshipping\CustomerHasPlatforms\Hydrators\CustomerHasPlatformsHydrateOrders;
use App\Actions\Ordering\Order\SubmitOrder;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\CustomerHasPlatform;
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
        $order = SubmitOrder::make()->action($order);


        if (!$order->customer_id && !$order->platform) {
            return $order;
        }

        $customerHasPlatform = CustomerHasPlatform::where('customer_id', $order->customer_id)
        ->where('platform_id', $order->platform_id)
        ->first();

        CustomerHasPlatformsHydrateOrders::dispatch($customerHasPlatform);

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
