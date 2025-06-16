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
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
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

        $stats['number_current_products'] = Arr::get($stats, 'number_products_state_active', 0) +
            Arr::get($stats, 'number_products_state_discontinuing', 0);

        UpdateProductCategory::make()->action(
            $subDepartment,
            [
                'state' => $this->getProductCategoryState($stats)
            ]
        );

        $subDepartment->stats()->update($stats);
    }


}
