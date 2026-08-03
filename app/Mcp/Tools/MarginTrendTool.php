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

#[Description('Monthly profitability trend for a shop from invoices: net sales, profit, margin percentage and invoice count per month. Use this for questions about margin, profit or profitability over time.')]
#[IsReadOnly]
class MarginTrendTool extends AikuTool
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
                COUNT(*) AS invoices,
                ROUND(SUM(net_amount)::numeric, 2) AS net,
                ROUND(SUM(profit_amount)::numeric, 2) AS profit,
                ROUND(100.0 * SUM(profit_amount) / NULLIF(SUM(net_amount), 0), 1) AS margin_pct
            FROM invoices
            WHERE shop_id = ? AND date BETWEEN ? AND ? AND deleted_at IS NULL AND in_process = false
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
