<?php

/*
 * author Louis Perez
 * created on 13-03-2026-08h-49m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterProductCategory\Hydrators;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterSubDepartmentHydrateSubDepartment implements ShouldBeUnique
{
    use WithActionUpdate;
    // use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(MasterProductCategory $masterSubDepartment): string
    {
        return $masterSubDepartment->id;
    }

    public function handle(MasterProductCategory $masterSubDepartment): void
    {


        if ($masterSubDepartment->type != MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
            return;
        }


        $stats = [
            'number_sub_departments' => DB::table('product_categories')->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)->where('master_product_category_id', $masterSubDepartment->id)->count(),
            'number_current_sub_departments' => DB::table('product_categories')->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)
                ->where('master_product_category_id', $masterSubDepartment->id)->whereIn('state', [
                ProductCategoryStateEnum::ACTIVE,
                ProductCategoryStateEnum::DISCONTINUING,
            ])->count()
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'sub_departments',
                field: 'state',
                enum: ProductCategoryStateEnum::class,
                models: ProductCategory::class,
                where: function ($q) use ($masterSubDepartment) {
                    $q->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)->where('master_product_category_id', $masterSubDepartment->id);
                }
            )
        );

        $masterSubDepartment->stats()->update($stats);
    }

    public string $commandSignature = 'hydrate:master_sub_department_stat {--mpc=}';

    public function asCommand(Command $command): void
    {
        $subDept = MasterProductCategory::where('slug', $command->option('mpc'))->first();

        if(!$subDept) return;
        $this->handle($subDept);
    }

}
