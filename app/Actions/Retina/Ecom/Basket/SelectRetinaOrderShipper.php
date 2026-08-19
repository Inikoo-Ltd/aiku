<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ecom\Basket;

use App\Actions\Ordering\Order\CalculateOrderShipping;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SelectRetinaOrderShipper extends RetinaAction
{
    public function handle(Order $order, array $modelData): Order
    {
        $shipperId = $modelData['shipper_id'];

        $shipperIds = collect($order->shippingZone?->shippers_price ?? [])->pluck('shipper_id');
        if (!$shipperIds->contains($shipperId)) {
            throw ValidationException::withMessages(['shipper_id' => __('This shipper is not available for your delivery address')]);
        }

        $order->update([
            'shipper_id'        => $shipperId,
            'is_shipper_locked' => true,
        ]);

        CalculateOrderShipping::run($order->refresh());
        CalculateOrderTotalAmounts::run($order);

        return $order;
    }

    public function authorize(ActionRequest $request): bool
    {
        $order = $request->route('order');

        return $order->customer_id == $this->customer->id && $order->state == OrderStateEnum::CREATING;
    }

    public function rules(): array
    {
        return [
            'shipper_id' => ['required', 'integer'],
        ];
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisation($request);

        return $this->handle($order, $this->validatedData);
    }
}
