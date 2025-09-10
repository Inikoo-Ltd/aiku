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
use App\Actions\Traits\WithActionUpdate;
use App\Models\Ordering\Order;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateRetinaOrderPremiumDispatch extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    private Order $order;

    public function handle(Order $order, array $modelData): Order
    {
        $order = UpdateOrderPremiumDispatch::make()->action($order, $modelData);

        return $order;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->order->customer_id == $request->user()->id;
    }

    public function rules(): array
    {
        return [
            'is_premium_dispatch'   => ['required', 'boolean'],
        ];
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->order = $order;
        $this->initialisation($request);

        return $this->handle($order, $this->validatedData);
    }
}
