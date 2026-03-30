<?php

/*
 * author Louis Perez
 * created on 25-03-2026-12h-39m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Dropshipping\Shopify\Fulfilment\UI;

use App\Actions\Dropshipping\Shopify\Fulfilment\CloseFulfillOrderToShopify;
use App\Actions\OrgAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Ordering\Order;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SyncOrderCancellationToShopify extends OrgAction
{
    public function handle(Order $order): void
    {
        if ($order->platform->type !== PlatformTypeEnum::SHOPIFY) {
            throw ValidationException::withMessages([
                'messages' => __('Order does not comes from Shopify')
            ]);
        }

        if ($order->state === OrderStateEnum::CANCELLED) {
            CloseFulfillOrderToShopify::run($order);
        } else {
            throw ValidationException::withMessages([
                'messages' => __('Unable to sync order status: (:__orderState) to Shopify', ['__orderState' => $order->state->value])
            ]);
        }
    }

    public function asController(Order $order, ActionRequest $request): void
    {
        $this->initialisationFromShop($order->shop, $request);

        $this->handle($order);
    }
}
