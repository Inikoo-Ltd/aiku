<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Mar 2023 05:16:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductCategoryHydrateFamilies implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(ProductCategory $productCategory): string
    {
        return $productCategory->id;
    }

    public function handle(ProductCategory $productCategory): void
    {
        if ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            return;
        }

        $stats = [
            'number_families' =>  DB::table('product_categories')
                ->where(function ($query) use ($productCategory) {
                    if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
                        $query->where('department_id', $productCategory->id);
                    } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                        $query->where('sub_department_id', $productCategory->id);
                    }
                })
                ->where('type', ProductCategoryTypeEnum::FAMILY->value)
                ->where('deleted_at', null)
                ->count()
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'families',
                field: 'state',
                enum: ProductCategoryStateEnum::class,
                models: ProductCategory::class,
                where: function ($q) use ($productCategory) {
                    $q->where(function ($query) use ($productCategory) {
                        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
                            $query->where('department_id', $productCategory->id);
                        } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                            $query->where('sub_department_id', $productCategory->id);
                        }
                    })->where('type', ProductCategoryTypeEnum::FAMILY);
                }
            )
        );

        $stats['number_current_families'] = Arr::get($stats, 'number_families_state_active', 0) +
            Arr::get($stats, 'number_families_state_discontinuing', 0);

        $productCategory->stats()->update($stats);
    }

}
