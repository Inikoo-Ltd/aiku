<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\Ordering\Order;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Look up one order by its reference and return its current state and key dates.')]
class OrderStatusTool extends AikuTool
{
    protected function permission(): ShopPermissionsEnum
    {
        return ShopPermissionsEnum::ORDERS_VIEW;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'shop'      => ['required', 'string'],
            'reference' => ['required', 'string'],
        ]);

        $shop = $this->authorisedShop($request);
        if (!$shop) {
            return Response::error('Shop not found or permission denied.');
        }

        $order = Order::where('shop_id', $shop->id)
            ->where('reference', $request->string('reference'))
            ->first();

        if (!$order) {
            return Response::error('Order not found.');
        }

        return Response::json([
            'reference'    => $order->reference,
            'state'        => $order->state->value,
            'created_at'   => $order->created_at?->toDateString(),
            'submitted_at' => $order->submitted_at?->toDateString(),
            'dispatched_at' => $order->dispatched_at?->toDateString(),
            'cancelled_at' => $order->cancelled_at?->toDateString(),
            'total_amount' => (float) $order->total_amount,
            'currency'     => $shop->currency->code,
            'customer'     => $order->customer?->contact_name,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'shop'      => $schema->string()->description('Shop slug')->required(),
            'reference' => $schema->string()->description('Order reference')->required(),
        ];
    }
}
