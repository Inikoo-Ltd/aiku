<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 25-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\ProductCategory\Hydrators;

use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
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
        $families = $department->getFamilies();
        // $stats = [
        //     'number_products' => $department->getproducts()->where('is_main', true)->whereNull('exclusive_for_customer_id')->count()
        // ];

        // $stats = array_merge(
        //     $stats,
        //     $this->getEnumStats(
        //         model: 'products',
        //         field: 'state',
        //         enum: ProductStateEnum::class,
        //         models: Product::class,
        //         where: function ($q) use ($department) {
        //             $q->where('is_main', true)->where('department_id', $department->id);
        //         }
        //     )
        // );

        // $stats['number_current_products'] = Arr::get($stats, 'number_products_state_active', 0) +
        //     Arr::get($stats, 'number_products_state_discontinuing', 0);

        // UpdateProductCategory::make()->action(
        //     $department,
        //     [
        //         'state' => $this->getProductCategoryState($stats)
        //     ]
        // );





        // $department->stats()->update($stats);
    }


}
