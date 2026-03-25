<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\ProductCategory\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class FamilyHydrateBestSellerProduct implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use HasGetProductCategoryState;

    public function getJobUniqueId(ProductCategory $family): string
    {
        return $family->id;
    }

    public function handle(ProductCategory $family): void
    {
        if ($family->type !== ProductCategoryTypeEnum::FAMILY) {
            return;
        }

        // Reset all products top_seller flag first
        Product::where('family_id', $family->id)
            ->where('top_seller', '>', 0)
            ->update(['top_seller' => null]);

        // Get the total count of main products for this family
        $totalProducts = Product::where('family_id', $family->id)
            ->where('is_main', true)
            ->whereNull('exclusive_for_customer_id')
            ->count();

        // Determine how many top products to mark
        $topCount = 0;
        if ($totalProducts >= 7) {
            $topCount = 3;
        } elseif ($totalProducts >= 5) {
            $topCount = 2;
        } elseif ($totalProducts >= 4) {
            $topCount = 1;
        }

        // If less than 4 products, don't update any
        if ($topCount === 0) {
            return;
        }

        $threeMonthsAgo = now()->subMonths(3)->format('Y-m');

        $topProducts = Product::query()
            ->where('products.family_id', $family->id)
            ->where('products.is_main', true)
            ->whereNull('products.exclusive_for_customer_id')
            ->join('assets', 'products.asset_id', '=', 'assets.id')
            ->join('asset_time_series', 'assets.id', '=', 'asset_time_series.asset_id')
            ->join('asset_time_series_records', 'asset_time_series.id', '=', 'asset_time_series_records.asset_time_series_id')
            ->where('asset_time_series_records.frequency', 'M')
            ->where('asset_time_series_records.period', '>=', $threeMonthsAgo)
            ->groupBy('products.id')
            ->havingRaw('SUM(COALESCE(asset_time_series_records.sales_external, 0) + COALESCE(asset_time_series_records.sales_internal, 0)) > 0')
            ->orderByRaw('SUM(COALESCE(asset_time_series_records.sales_external, 0) + COALESCE(asset_time_series_records.sales_internal, 0)) DESC')
            ->select('products.id')
            ->limit($topCount)
            ->get();

        foreach ($topProducts as $index => $product) {
            Product::where('id', $product->id)
                ->update(['top_seller' => $index + 1]);
        }
    }

}
