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

#[Description('Monthly customer registration cohorts for a shop: how many registered, how many placed a first order, conversion rate and median days from registration to first order. Use this for questions about customer acquisition, activation or registration-to-order conversion.')]
#[IsReadOnly]
class CustomerConversionTool extends AikuTool
{
    protected function permission(): ShopPermissionsEnum
    {
        return ShopPermissionsEnum::CRM_VIEW;
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
            "SELECT to_char(date_trunc('month', c.registered_at), 'YYYY-MM') AS cohort,
                COUNT(*) AS registered,
                COUNT(cs.first_order_date) AS converted,
                ROUND(100.0 * COUNT(cs.first_order_date) / NULLIF(COUNT(*), 0), 1) AS conversion_pct,
                ROUND((percentile_cont(0.5) WITHIN GROUP (
                    ORDER BY EXTRACT(EPOCH FROM (cs.first_order_date - c.registered_at::timestamp)) / 86400
                ))::numeric, 1) AS median_days_to_first_order
            FROM customers c
            LEFT JOIN customer_stats cs ON cs.customer_id = c.id
            WHERE c.shop_id = ? AND c.registered_at BETWEEN ? AND ? AND c.deleted_at IS NULL
            GROUP BY 1
            ORDER BY 1",
            [$shop->id, $request->date('from'), $request->date('to')->endOfDay()]
        );

        return Response::json([
            'shop'    => $shop->name,
            'from'    => $request->string('from'),
            'to'      => $request->string('to'),
            'cohorts' => $rows,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'shop' => $schema->string()->description('Shop slug or code, e.g. eu or EU')->required(),
            'from' => $schema->string()->description('Registration start date (Y-m-d)')->required(),
            'to'   => $schema->string()->description('Registration end date (Y-m-d), inclusive')->required(),
        ];
    }
}
