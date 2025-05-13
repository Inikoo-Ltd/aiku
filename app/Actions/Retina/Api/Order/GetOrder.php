<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-10h-24m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Api\Order;

use App\Http\Resources\Api\OrderResource;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetOrder
{
    use AsAction;
    use WithAttributes;

    public function handle(Order $order): Order
    {
        return $order;
    }

    public function jsonResponse(Order $order)
    {
        return OrderResource::make($order);
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        return $this->handle($order);
    }
}
