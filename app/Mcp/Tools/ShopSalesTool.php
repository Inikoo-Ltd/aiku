<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\Catalogue\ShopTimeSeries;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Sales summary for a shop over a date range: orders, invoices, sales revenue and customers invoiced.')]
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

        $sales = ShopTimeSeries::where('shop_id', $shop->id)
            ->where('shop_time_series.frequency', TimeSeriesFrequencyEnum::DAILY)
            ->join('shop_time_series_records', 'shop_time_series_records.shop_time_series_id', '=', 'shop_time_series.id')
            ->whereBetween('shop_time_series_records.from', [$request->date('from'), $request->date('to')->endOfDay()])
            ->selectRaw('coalesce(sum(orders), 0) as number_orders, coalesce(sum(invoices), 0) as number_invoices, coalesce(sum(refunds), 0) as number_refunds, coalesce(sum(sales_external), 0) as sales, coalesce(sum(customers_invoiced), 0) as customers_invoiced')
            ->first();

        return Response::json([
            'shop'               => $shop->name,
            'from'               => $request->string('from'),
            'to'                 => $request->string('to'),
            'number_orders'      => (int) $sales->number_orders,
            'number_invoices'    => (int) $sales->number_invoices,
            'number_refunds'     => (int) $sales->number_refunds,
            'sales'              => (float) $sales->sales,
            'customers_invoiced' => (int) $sales->customers_invoiced,
            'currency'           => $shop->currency->code,
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
