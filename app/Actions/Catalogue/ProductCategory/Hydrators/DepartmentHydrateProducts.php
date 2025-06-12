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
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class DepartmentHydrateProducts
{
    use AsAction;
    use WithEnumStats;
    use HasGetProductCategoryState;

    private ProductCategory $department;

    public function __construct(ProductCategory $department)
    {
        $this->department = $department;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->department->id))->dontRelease()];
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

        $stats['number_current_products'] = Arr::get($stats, 'number_products_state_active', 0) +
            Arr::get($stats, 'number_products_state_discontinuing', 0);

        UpdateProductCategory::make()->action(
            $department,
            [
                'state' => $this->getProductCategoryState($stats)
            ]
        );

        $department->stats()->update($stats);
    }


}
