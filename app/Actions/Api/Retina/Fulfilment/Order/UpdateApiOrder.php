<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-10h-24m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Fulfilment\Order;

use App\Actions\Api\Retina\Fulfilment\Resource\OrderApiResource;
use App\Actions\Ordering\Order\UpdateOrder;
use App\Actions\RetinaApiAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateApiOrder extends RetinaApiAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Order $order, array $modelData): Order|JsonResponse
    {
        if ($order->state != OrderStateEnum::CREATING) {
            return response()->json([
                'message' => 'This order is already in the "' . $order->state->value . '" state and cannot be updated.',
            ]);
        }
        $order = UpdateOrder::make()->action($order, $modelData);

        return $order;
    }

    public function rules(): array
    {
        return [
            'public_notes'        => ['required', 'nullable', 'string', 'max:4000'],
        ];
    }

    public function jsonResponse(Order $order)
    {
        return OrderApiResource::make($order)
            ->additional([
                'message' => __('Order updated successfully'),
            ]);
    }

    public function asController(Order $order, ActionRequest $request): Order|JsonResponse
    {
        $this->initialisationFromFulfilment($request);

        return $this->handle($order, $this->validatedData);
    }
}
