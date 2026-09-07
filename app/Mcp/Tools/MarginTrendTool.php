<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Traits\WithMarginData;
use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Monthly profitability trend for a shop, computed from invoiced product lines and their picking costs, the same basis as the margin figures on the order and invoice screens. Lines without cost data are excluded from net/profit and counted per month. Use this for questions about margin, profit or profitability over time.')]
#[IsReadOnly]
class MarginTrendTool extends AikuTool
{
    use WithMarginData;

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

        $cost = '('.$this->actualCostSql('invoice_transactions.transaction_id').') * invoice_transactions.quantity / NULLIF(margin_transactions.quantity_ordered, 0)';

        $rows = DB::select(
            "SELECT to_char(date_trunc('month', invoices.date), 'YYYY-MM') AS month,
                COUNT(DISTINCT invoices.id) AS invoices,
                ROUND(SUM(invoice_transactions.net_amount) FILTER (WHERE $cost IS NOT NULL)::numeric, 2) AS net,
                ROUND(SUM(invoice_transactions.net_amount * (invoice_transactions.org_net_amount - $cost) / NULLIF(invoice_transactions.org_net_amount, 0)) FILTER (WHERE $cost IS NOT NULL)::numeric, 2) AS profit,
                ROUND(100.0 * SUM((invoice_transactions.org_net_amount - $cost)) FILTER (WHERE $cost IS NOT NULL)
                    / NULLIF(SUM(invoice_transactions.org_net_amount) FILTER (WHERE $cost IS NOT NULL), 0), 1) AS margin_pct,
                COUNT(*) FILTER (WHERE $cost IS NULL) AS lines_without_cost
            FROM invoice_transactions
            JOIN invoices ON invoices.id = invoice_transactions.invoice_id
            LEFT JOIN transactions AS margin_transactions ON margin_transactions.id = invoice_transactions.transaction_id
            WHERE invoices.shop_id = ? AND invoices.date BETWEEN ? AND ?
                AND invoices.deleted_at IS NULL AND invoices.in_process = false
                AND invoice_transactions.deleted_at IS NULL AND invoice_transactions.model_type = 'Product'
            GROUP BY 1
            ORDER BY 1",
            [$shop->id, $request->date('from'), $request->date('to')->endOfDay()]
        );

        return Response::json([
            'shop'     => $shop->name,
            'from'     => $request->string('from'),
            'to'       => $request->string('to'),
            'currency' => $shop->currency->code,
            'note'     => 'Product lines only, costed lines only; lines_without_cost shows how many were excluded per month.',
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
