<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-10h-24m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Api\Order;

use App\Actions\Ordering\Order\UpdateOrder;
use App\Http\Resources\Sales\OrderResource;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateApiOrder
{
    use AsAction;
    use WithAttributes;

    public function handle(Order $order, array $modelData): Order
    {
        $order = UpdateOrder::make()->action($order, $modelData);

        return $order;
    }

    public function rules(): array
    {
        return [
            'public_notes'        => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }

    public function jsonResponse(Order $order)
    {
        return OrderResource::make($order)
            ->additional([
                'meta' => [
                    'message' => __('Order updated successfully'),
                ],
            ]);
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->fillFromRequest($request);
        $validatedData = $this->validateAttributes();

        return $this->handle($order, $validatedData);
    }
}
