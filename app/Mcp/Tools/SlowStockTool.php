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

#[Description('Dead and slow-moving stock: active for-sale products of a shop holding stock but with sales below a threshold over the date range, ordered by stock value tied up. Use this for questions about dead stock, overstock, slow movers or capital tied up in inventory.')]
#[IsReadOnly]
class SlowStockTool extends AikuTool
{
    protected function permission(): ShopPermissionsEnum
    {
        return ShopPermissionsEnum::PRODUCTS_VIEW;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'shop'      => ['required', 'string'],
            'from'      => ['required', 'date'],
            'to'        => ['required', 'date', 'after_or_equal:from'],
            'max_sales' => ['sometimes', 'numeric', 'min:0'],
            'limit'     => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $shop = $this->authorisedShop($request);
        if (!$shop) {
            return $this->shopNotFoundError($request);
        }

        $rows = DB::select(
            'WITH sold AS (
                SELECT model_id, SUM(net_amount) AS sales, SUM(quantity) AS quantity
                FROM invoice_transactions
                WHERE shop_id = ? AND model_type = ? AND date BETWEEN ? AND ? AND deleted_at IS NULL
                GROUP BY model_id
            )
            SELECT p.code, p.name, p.state, p.available_quantity, p.price,
                ROUND((p.available_quantity * p.price / NULLIF(p.units, 0))::numeric, 2) AS stock_value,
                COALESCE(ROUND(s.sales::numeric, 2), 0) AS sales,
                COALESCE(s.quantity, 0) AS quantity_sold
            FROM products p
            LEFT JOIN sold s ON s.model_id = p.id
            WHERE p.shop_id = ? AND p.deleted_at IS NULL AND p.is_for_sale = true
                AND p.state = ? AND p.available_quantity > 0
                AND COALESCE(s.sales, 0) <= ?
            ORDER BY p.available_quantity * p.price / NULLIF(p.units, 0) DESC NULLS LAST
            LIMIT ?',
            [
                $shop->id,
                'Product',
                $request->date('from'),
                $request->date('to')->endOfDay(),
                $shop->id,
                'active',
                $request->float('max_sales', 0),
                $request->integer('limit', 20),
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
            'shop'      => $schema->string()->description('Shop slug or code, e.g. eu or EU')->required(),
            'from'      => $schema->string()->description('Start date (Y-m-d)')->required(),
            'to'        => $schema->string()->description('End date (Y-m-d), inclusive')->required(),
            'max_sales' => $schema->number()->description('Only products with sales at or below this amount in the range, default 0 (no sales at all)'),
            'limit'     => $schema->integer()->description('Maximum products to return, default 20')->min(1)->max(100),
        ];
    }
}
