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

#[Description('Products of a shop with the most refunded value over a date range: net refunded amount, refunded quantity and number of refund invoices per product. Use this for questions about refunds, returns or products losing money.')]
#[IsReadOnly]
class RefundsByProductTool extends AikuTool
{
    protected function permission(): ShopPermissionsEnum
    {
        return ShopPermissionsEnum::ORDERS_VIEW;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'shop'  => ['required', 'string'],
            'from'  => ['required', 'date'],
            'to'    => ['required', 'date', 'after_or_equal:from'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $shop = $this->authorisedShop($request);
        if (!$shop) {
            return $this->shopNotFoundError($request);
        }

        $rows = DB::select(
            'SELECT p.code, p.name,
                ROUND(SUM(it.net_amount)::numeric, 2) AS refunded_net,
                ROUND(SUM(it.quantity)::numeric, 1) AS refunded_quantity,
                COUNT(DISTINCT it.invoice_id) AS refund_invoices
            FROM invoice_transactions it
            JOIN products p ON p.id = it.model_id AND it.model_type = ?
            WHERE it.shop_id = ? AND it.is_refund = true AND it.date BETWEEN ? AND ? AND it.deleted_at IS NULL
            GROUP BY p.code, p.name
            ORDER BY SUM(it.net_amount) ASC
            LIMIT ?',
            [
                'Product',
                $shop->id,
                $request->date('from'),
                $request->date('to')->endOfDay(),
                $request->integer('limit', 15),
            ]
        );

        return Response::json([
            'shop'     => $shop->name,
            'from'     => $request->string('from'),
            'to'       => $request->string('to'),
            'currency' => $shop->currency->code,
            'products' => $rows,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'shop'  => $schema->string()->description('Shop slug or code, e.g. eu or EU')->required(),
            'from'  => $schema->string()->description('Start date (Y-m-d)')->required(),
            'to'    => $schema->string()->description('End date (Y-m-d), inclusive')->required(),
            'limit' => $schema->integer()->description('Maximum products to return, default 15')->min(1)->max(50),
        ];
    }
}
