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
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SubDepartmentHydrateProducts implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use HasGetProductCategoryState;

    public string $jobQueue = 'hydrators-slave';

    public function getJobUniqueId(?int $subDepartmentId): string
    {
        return $subDepartmentId ?? 'empty';
    }

    public function handle(?int $subDepartmentId): void
    {

        if (!$subDepartmentId) {
            return;
        }

        $subDepartment = ProductCategory::on('aiku_no_sticky')->find($subDepartmentId);


        if (!$subDepartment || $subDepartment->type !== ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            return;
        }

        $stats = [
            'number_products' => DB::connection('aiku_no_sticky')->table('products')
                ->whereNull('deleted_at')
                ->where('sub_department_id', $subDepartment->id)
                ->where('is_main', true)->whereNull('exclusive_for_customer_id')->count()
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
                },
                connection: 'aiku_no_sticky'
            )
        );

        $numberCurrentProductsActiveForSale = Product::on('aiku_no_sticky')->where('sub_department_id', $subDepartment->id)->where('is_for_sale', true)
            ->where('state', ProductStateEnum::ACTIVE)
            ->count();
        $numberCurrentProductsDiscontinuingForSale = Product::on('aiku_no_sticky')->where('sub_department_id', $subDepartment->id)->where('is_for_sale', true)
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
