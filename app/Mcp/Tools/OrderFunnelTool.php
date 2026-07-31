<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Monthly order funnel for a shop: dispatched orders with net sales and average order value, plus abandoned baskets (orders stuck in creating state) with the value left behind. Use this for questions about order volume, abandoned baskets or checkout conversion.')]
#[IsReadOnly]
class OrderFunnelTool extends AikuTool
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
            return $this->shopNotFoundError($request);
        }

        $rows = DB::select(
            "SELECT to_char(date_trunc('month', date), 'YYYY-MM') AS month,
                COUNT(*) FILTER (WHERE state = 'dispatched') AS dispatched_orders,
                ROUND(COALESCE(SUM(net_amount) FILTER (WHERE state = 'dispatched'), 0)::numeric, 2) AS dispatched_net,
                ROUND(AVG(net_amount) FILTER (WHERE state = 'dispatched')::numeric, 2) AS average_order_value,
                COUNT(*) FILTER (WHERE state = 'cancelled') AS cancelled_orders,
                COUNT(*) FILTER (WHERE state = 'creating') AS abandoned_baskets,
                ROUND(COALESCE(SUM(net_amount) FILTER (WHERE state = 'creating'), 0)::numeric, 2) AS abandoned_net
            FROM orders
            WHERE shop_id = ? AND date BETWEEN ? AND ? AND deleted_at IS NULL
            GROUP BY 1
            ORDER BY 1",
            [$shop->id, $request->date('from'), $request->date('to')->endOfDay()]
        );

        return Response::json([
            'shop'     => $shop->name,
            'from'     => $request->string('from'),
            'to'       => $request->string('to'),
            'currency' => $shop->currency->code,
            'months'   => $rows,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'shop' => $schema->string()->description('Shop slug or code, e.g. eu or EU')->required(),
            'from' => $schema->string()->description('Start date (Y-m-d)')->required(),
            'to'   => $schema->string()->description('End date (Y-m-d), inclusive')->required(),
        ];
    }
}
