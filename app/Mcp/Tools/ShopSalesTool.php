<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\Ordering\Order;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Number of orders and net sales for a shop over a date range. Excludes unsubmitted and cancelled orders.')]
class ShopSalesTool extends AikuTool
{
    protected function permission(): ShopPermissionsEnum
    {
        return ShopPermissionsEnum::ORDERS_VIEW;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'shop' => ['required', 'string'],
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $shop = $this->authorisedShop($request);
        if (!$shop) {
            return Response::error('Shop not found or permission denied.');
        }

        $sales = Order::where('shop_id', $shop->id)
            ->whereNotIn('state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])
            ->whereBetween('date', [$request->date('from'), $request->date('to')->endOfDay()])
            ->selectRaw('count(*) as number_orders, coalesce(sum(net_amount), 0) as net_amount')
            ->first();

        return Response::json([
            'shop'          => $shop->name,
            'from'          => $request->string('from'),
            'to'            => $request->string('to'),
            'number_orders' => (int) $sales->number_orders,
            'net_amount'    => (float) $sales->net_amount,
            'currency'      => $shop->currency->code,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'shop' => $schema->string()->description('Shop slug')->required(),
            'from' => $schema->string()->description('Start date (Y-m-d)')->required(),
            'to'   => $schema->string()->description('End date (Y-m-d), inclusive')->required(),
        ];
    }
}
