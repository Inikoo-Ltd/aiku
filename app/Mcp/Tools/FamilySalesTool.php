<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Sales by product family for a shop over a date range, sorted worst-first to surface underperforming families. Families with no sales in the range are included.')]
class FamilySalesTool extends AikuTool
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

        $families = ProductCategory::where('product_categories.shop_id', $shop->id)
            ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->leftJoin('product_category_time_series', function ($join) {
                $join->on('product_category_time_series.product_category_id', '=', 'product_categories.id')
                    ->where('product_category_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value);
            })
            ->leftJoin('product_category_time_series_records', function ($join) use ($request) {
                $join->on('product_category_time_series_records.product_category_time_series_id', '=', 'product_category_time_series.id')
                    ->whereBetween('product_category_time_series_records.from', [
                        $request->date('from'),
                        $request->date('to')->endOfDay(),
                    ]);
            })
            ->groupBy('product_categories.id', 'product_categories.code', 'product_categories.name')
            ->selectRaw('product_categories.code, product_categories.name, coalesce(sum(product_category_time_series_records.sales_external), 0) as sales')
            ->orderBy('sales')
            ->limit($request->integer('limit', 15))
            ->get()
            ->map(fn ($family) => [
                'code'  => $family->code,
                'name'  => $family->name,
                'sales' => (float) $family->sales,
            ])
            ->all();

        return Response::json([
            'shop'     => $shop->name,
            'from'     => $request->string('from'),
            'to'       => $request->string('to'),
            'currency' => $shop->currency->code,
            'families' => $families,
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
            'limit' => $schema->integer()->description('Maximum families to return, default 15')->minimum(1)->maximum(50),
        ];
    }
}
