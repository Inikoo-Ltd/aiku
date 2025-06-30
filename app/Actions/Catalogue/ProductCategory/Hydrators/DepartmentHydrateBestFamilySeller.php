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

        $families = $department->getFamilies();

        // Sort by sales_all in descending order, only if sales_all is not null
        $topFamilies = $families->filter(function ($family) {
            return isset($family->salesIntervals->sales_all) && $family->salesIntervals->sales_all > 0;
        })->sortByDesc(function ($family) {
            return $family->salesIntervals->sales_all;
        });


        // Apply rules based on total families count
        $totalFamilies = $families->count();
        if ($totalFamilies >= 4) {
            $topCount = 1;
            if ($totalFamilies >= 5) {
                $topCount = 2;
                if ($totalFamilies >= 7) {
                    $topCount = 3;
                }
            }
            $topFamilies = $topFamilies->take($topCount);
        } else {
            // If less than 4 families, don't update any
            $topFamilies = collect();
        }

        // Reset all families top_seller flag first
        foreach ($families as $family) {
            if ($family->top_seller > 0) {
                $family->update([
                    'top_seller' => null
                ]);
            }
        }

        // Update top families
        $index = 0;
        foreach ($topFamilies as $family) {
            $family->update([
                'top_seller' => $index + 1,
            ]);
            $index++;
        }
    }

}
