<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Jun 2024 20:15:28 Central European Summer Time, Abu Dhabi Airport
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DepartmentHydrateSubDepartments implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(ProductCategory $productCategory): string
    {
        return $productCategory->id;
    }

    public function handle(ProductCategory $productCategory): void
    {

        if ($productCategory->type !== ProductCategoryTypeEnum::DEPARTMENT) {
            return;
        }

        $stats = [
            'number_sub_departments' => DB::table('product_categories')
                ->where('department_id', $productCategory->id)
                ->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)
                ->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'sub_departments',
                field: 'state',
                enum: ProductCategoryStateEnum::class,
                models: ProductCategory::class,
                where: function ($q) use ($productCategory) {
                    $q->where('department_id', $productCategory->id)->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT);
                }
            )
        );

        $productCategory->stats()->update($stats);
    }


}
