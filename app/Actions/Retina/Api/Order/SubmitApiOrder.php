<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-10h-41m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Api\Order;

use App\Actions\Ordering\Order\SubmitOrder;
use App\Http\Resources\Sales\OrderResource;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SubmitApiOrder
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
        return OrderResource::make($order)
            ->additional([
                'meta' => [
                    'message' => __('Order submitted successfully'),
                ],
            ]);
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        return $this->handle($order);
    }
}
