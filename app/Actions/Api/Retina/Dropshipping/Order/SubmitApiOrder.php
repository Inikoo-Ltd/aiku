<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-10h-41m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Dropshipping\Order;

use App\Actions\Api\Retina\Dropshipping\Resource\OrderApiResource;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Retina\Dropshipping\Orders\PayOrderAsync;
use App\Actions\RetinaApiAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use Exception;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class SubmitApiOrder extends RetinaApiAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order): Order|JsonResponse
    {
        if ($order->customer_id != $this->customer->id || $order->shop_id != $this->shop->id) {
            return response()->json([
                'message' => "Unable to make modifications for this order"
            ], 403);
        }

        if ($order->state != OrderStateEnum::CREATING) {
            return response()->json([
                'message' => "This order is already in the '{$order->state->value}' state and cannot be updated."
            ], 409);
        }

        try {
            PayOrderAsync::run($order);
        } catch (Exception $e) {
            Sentry::captureException($e);
        }

        return SubmitOrder::make()->action($order);
    }

    public function jsonResponse(JsonResponse|Order $result): OrderApiResource|\Illuminate\Http\Resources\Json\JsonResource|JsonResponse
    {
        if ($result instanceof JsonResponse) {
            return $result;
        }

        return OrderApiResource::make($result)
            ->additional([
                'message' => __('Order submitted successfully'),
            ]);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisationFromDropshipping($request);

        return $this->handle($order);
    }
}
