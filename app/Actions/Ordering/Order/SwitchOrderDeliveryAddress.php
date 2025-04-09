<?php

/*
 * author Arya Permana - Kirin
 * created on 07-04-2025-09h-53m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

class SwitchOrderDeliveryAddress extends OrgAction
{
    use WithActionUpdate;

    public function handle(Order $order, array $modelData): Order
    {
        if (isset($modelData['delivery_address_id'])) {
            $order->delivery_address_id                               = $modelData['delivery_address_id'];
            $order->save();
        }

        return $order;
    }

    public function rules(): array
    {
        return [
            'delivery_address_id' => ['sometimes', 'nullable', 'exists:addresses,id'],
        ];
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData);
    }
}
