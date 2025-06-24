<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-10h-24m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Group\Order;

use App\Actions\Api\Group\Resources\OrderApiResource;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

class ShowApiOrder extends OrgAction
{
    public function handle(Order $order): Order
    {
        return $order;
    }

    public function jsonResponse(Order $order): \Illuminate\Http\Resources\Json\JsonResource|OrderApiResource
    {
        return OrderApiResource::make($order);
    }

    public function asController(Shop $shop, Order $order, ActionRequest $request): Order
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order);
    }
}
