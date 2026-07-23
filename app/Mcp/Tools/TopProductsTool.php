<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\Catalogue\Asset;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Best-selling products for a shop over a date range, by quantity sold, with revenue.')]
class TopProductsTool extends AikuTool
{
    protected function permission(): ShopPermissionsEnum
    {
        return ShopPermissionsEnum::PRODUCTS_VIEW;
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
            return Response::error('Shop not found or permission denied.');
        }

        $products = Asset::where('assets.shop_id', $shop->id)
            ->join('asset_time_series', function ($join) {
                $join->on('asset_time_series.asset_id', '=', 'assets.id')
                    ->where('asset_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value);
            })
            ->join('asset_time_series_records', function ($join) use ($request) {
                $join->on('asset_time_series_records.asset_time_series_id', '=', 'asset_time_series.id')
                    ->whereBetween('asset_time_series_records.from', [
                        $request->date('from'),
                        $request->date('to')->endOfDay(),
                    ]);
            })
            ->groupBy('assets.id', 'assets.code', 'assets.name')
            ->selectRaw('assets.code, assets.name, coalesce(sum(asset_time_series_records.sold), 0) as quantity, coalesce(sum(asset_time_series_records.sales_external), 0) as sales')
            ->orderByDesc('quantity')
            ->limit($request->integer('limit', 10))
            ->get()
            ->map(fn ($row) => [
                'code'     => $row->code,
                'name'     => $row->name,
                'quantity' => (float) $row->quantity,
                'sales'    => (float) $row->sales,
            ])
            ->all();

        return Response::json([
            'shop'     => $shop->name,
            'from'     => $request->string('from'),
            'to'       => $request->string('to'),
            'currency' => $shop->currency->code,
            'products' => $products,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'shop'  => $schema->string()->description('Shop slug')->required(),
            'from'  => $schema->string()->description('Start date (Y-m-d)')->required(),
            'to'    => $schema->string()->description('End date (Y-m-d), inclusive')->required(),
            'limit' => $schema->integer()->description('Maximum products to return, default 10')->minimum(1)->maximum(50),
        ];
    }
}
