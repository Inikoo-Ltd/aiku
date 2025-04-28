<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 23:18:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterDepartmentHydrateDepartments implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(MasterProductCategory $masterDepartment): string
    {
        return $masterDepartment->id;
    }

    public function handle(MasterProductCategory $masterDepartment): void
    {


        if ($masterDepartment->type != MasterProductCategoryTypeEnum::DEPARTMENT) {
            return;
        }


        $stats = [
            'number_departments' => DB::table('product_categories')->where('type', ProductCategoryTypeEnum::DEPARTMENT)->where('master_product_category_id', $masterDepartment->id)->count(),
            'number_current_departments' => DB::table('product_categories')->where('type', ProductCategoryTypeEnum::DEPARTMENT)->where('master_product_category_id', $masterDepartment->id)->whereIn('state', [
                ShopStateEnum::OPEN,
                ShopStateEnum::CLOSING_DOWN,
            ])->count()
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'departments',
                field: 'state',
                enum: ProductCategoryStateEnum::class,
                models: ProductCategory::class,
                where: function ($q) use ($masterDepartment) {
                    $q->where('type', ProductCategoryTypeEnum::DEPARTMENT)->where('master_product_category_id', $masterDepartment->id);
                }
            )
        );

        $masterDepartment->stats()->update($stats);
    }


}
