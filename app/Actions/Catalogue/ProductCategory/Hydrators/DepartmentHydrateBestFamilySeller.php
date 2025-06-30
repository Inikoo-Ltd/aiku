<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 25-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\ProductCategory\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class DepartmentHydrateBestFamilySeller implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use HasGetProductCategoryState;

    public function getJobUniqueId(ProductCategory $department): string
    {
        return $department->id;
    }

    public function handle(ProductCategory $department): void
    {
        if ($department->type !== ProductCategoryTypeEnum::DEPARTMENT) {
            return;
        }

        // Reset all families top_seller flag first
        ProductCategory::where('parent_id', $department->id)
            ->where('department_id', $department->id)
            ->where('type', ProductCategoryTypeEnum::FAMILY)
            ->where('top_seller', '>', 0)
            ->update(['top_seller' => null]);

        // Get the total count of families for this department
        $totalFamilies = ProductCategory::where('parent_id', $department->id)
            ->where('type', ProductCategoryTypeEnum::FAMILY)
            ->count();

        // Determine how many top families to mark
        $topCount = 0;
        if ($totalFamilies >= 7) {
            $topCount = 3;
        } elseif ($totalFamilies >= 5) {
            $topCount = 2;
        } elseif ($totalFamilies >= 4) {
            $topCount = 1;
        }

        // If less than 4 families, don't update any
        if ($topCount === 0) {
            return;
        }

        // Get top selling families directly with a query
        $topFamilies = ProductCategory::query()
            ->where('department_id', $department->id)
            ->where('type', ProductCategoryTypeEnum::FAMILY)
            ->join('product_category_sales_intervals', 'product_categories.id', '=', 'product_category_sales_intervals.product_category_id')
            ->whereNotNull('product_category_sales_intervals.sales_all')
            ->where('product_category_sales_intervals.sales_all', '>', 0)
            ->orderBy('product_category_sales_intervals.sales_all', 'desc')
            ->select('product_categories.id')
            ->limit($topCount)
            ->get();

        // Update top families with their ranking
        foreach ($topFamilies as $index => $family) {
            ProductCategory::where('id', $family->id)
                ->update(['top_seller' => $index + 1]);
        }
    }

}
