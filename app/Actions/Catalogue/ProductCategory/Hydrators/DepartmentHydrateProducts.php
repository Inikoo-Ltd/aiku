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
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DepartmentHydrateProducts implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use HasGetProductCategoryState;

    public string $jobQueue = 'hydrators-slave';

    public function getJobUniqueId(?int $departmentId): string
    {
        return $departmentId ?? 'empty';
    }

    public function handle(?int $departmentId): void
    {
        if (!$departmentId) {
            return;
        }

        $department = ProductCategory::on('aiku_no_sticky')->find($departmentId);

        if (!$department || $department->type !== ProductCategoryTypeEnum::DEPARTMENT) {
            return;
        }

        $stats = [
            'number_products' => DB::connection('aiku_no_sticky')->table('products')
                ->whereNull('deleted_at')
                ->where('department_id', $department->id)
                ->where('is_main', true)->whereNull('exclusive_for_customer_id')->count()
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
                },
                connection: 'aiku_no_sticky'
            )
        );

        $numberCurrentProductsActiveForSale = Product::on('aiku_no_sticky')->where('department_id', $department->id)->where('is_for_sale', true)
            ->where('state', ProductStateEnum::ACTIVE)
            ->count();
        $numberCurrentProductsDiscontinuingForSale = Product::on('aiku_no_sticky')->where('department_id', $department->id)->where('is_for_sale', true)
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
