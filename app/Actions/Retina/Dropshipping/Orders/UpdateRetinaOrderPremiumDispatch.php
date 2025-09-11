<?php

/*
 * author Arya Permana - Kirin
 * created on 09-05-2025-09h-20m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Ordering\Order\UpdateOrderPremiumDispatch;
use App\Actions\RetinaAction;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaOrderPremiumDispatch extends RetinaAction
{
    public function handle(Order $order, array $modelData): Order
    {
        return UpdateOrderPremiumDispatch::run($order, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        $order = $request->route('order');

        return $order->customer_id == $this->customer->id;
    }

    public function rules(): array
    {
        return [
            'is_premium_dispatch' => ['required', 'boolean'],
        ];
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisation($request);

        return $this->handle($order, $this->validatedData);
    }
}
