<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 23 May 2025 09:33:30 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\Hydrators;

use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class SubDepartmentHydrateProducts implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use HasGetProductCategoryState;

    public function getJobUniqueId(ProductCategory $subDepartment): string
    {
        return $subDepartment->id;
    }

    public function handle(ProductCategory $subDepartment): void
    {
        if ($subDepartment->type !== ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            return;
        }

        $stats = [
            'number_products' => $subDepartment->getproducts()->where('is_main', true)->whereNull('exclusive_for_customer_id')->count()
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'products',
                field: 'state',
                enum: ProductStateEnum::class,
                models: Product::class,
                where: function ($q) use ($subDepartment) {
                    $q->where('is_main', true)->where('sub_department_id', $subDepartment->id);
                }
            )
        );

        $numberCurrentProductsActiveForSale = Product::where('sub_department_id', $subDepartment->id)->where('is_for_sale', true)
            ->where('state', ProductStateEnum::ACTIVE)
            ->count();
        $numberCurrentProductsDiscontinuingForSale = Product::where('sub_department_id', $subDepartment->id)->where('is_for_sale', true)
            ->where('state', ProductStateEnum::DISCONTINUING)
            ->count();

        $stats['number_current_products'] = $numberCurrentProductsActiveForSale + $numberCurrentProductsDiscontinuingForSale;

        UpdateProductCategory::make()->action(
            $subDepartment,
            [
                'state' => $this->getProductCategoryState($stats, $numberCurrentProductsActiveForSale, $numberCurrentProductsDiscontinuingForSale)
            ]
        );

        $subDepartment->stats()->update($stats);
    }


}
