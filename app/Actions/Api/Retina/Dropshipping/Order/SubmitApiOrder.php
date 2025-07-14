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
use App\Actions\Retina\Dropshipping\Orders\PayOrderAsync;
use App\Actions\RetinaApiAction;
use App\Models\Ordering\Order;
use Exception;
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
    public function handle(Order $order): Order
    {
        try {
            PayOrderAsync::run($order);
        } catch (Exception $e) {
            Sentry::captureException($e);
        }

        return SubmitOrder::make()->action($order);
    }

    public function jsonResponse(Order $order): OrderApiResource|\Illuminate\Http\Resources\Json\JsonResource
    {
        return OrderApiResource::make($order)
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
