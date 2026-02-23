<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Mar 2023 05:16:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\Hydrators;

use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class DepartmentHydrateProducts implements ShouldBeUnique
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
        $stats = [
            'number_products' => $department->getproducts()->where('is_main', true)->whereNull('exclusive_for_customer_id')->count()
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'products',
                field: 'state',
                enum: ProductStateEnum::class,
                models: Product::class,
                where: function ($q) use ($department) {
                    $q->where('is_main', true)->where('department_id', $department->id);
                }
            )
        );

        $numberCurrentProductsActiveForSale = Product::where('department_id', $department->id)->where('is_for_sale', true)
            ->where('state', ProductStateEnum::ACTIVE)
            ->count();
        $numberCurrentProductsDiscontinuingForSale = Product::where('department_id', $department->id)->where('is_for_sale', true)
            ->where('state', ProductStateEnum::DISCONTINUING)
            ->count();

        $stats['number_current_products'] = $numberCurrentProductsActiveForSale + $numberCurrentProductsDiscontinuingForSale;

        UpdateProductCategory::make()->action(
            $department,
            [
                'state' => $this->getProductCategoryState($stats, $numberCurrentProductsActiveForSale, $numberCurrentProductsDiscontinuingForSale)
            ]
        );

        $department->stats()->update($stats);
    }


}
