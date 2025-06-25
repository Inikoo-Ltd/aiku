<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-10h-41m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Dropshipping\Order;

use App\Actions\Api\Retina\Dropshipping\Resource\OrderApiResource;
use App\Actions\Ordering\Order\SubmitOrder;
use App\Actions\RetinaApiAction;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SubmitApiOrder extends RetinaApiAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Order $order): Order
    {
        $order = SubmitOrder::make()->action($order);

        return $order;
    }

    public function jsonResponse(Order $order)
    {
        return OrderApiResource::make($order)
            ->additional([
                'message' => __('Order submitted successfully'),
            ]);
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisationFromDropshipping($request);
        return $this->handle($order);
    }
}
