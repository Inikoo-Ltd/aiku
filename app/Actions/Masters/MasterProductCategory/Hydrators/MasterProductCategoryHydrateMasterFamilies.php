<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Apr 2025 14:43:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\Hydrators;

use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterProductCategoryHydrateMasterFamilies implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(MasterProductCategory $masterProductCategory): string
    {
        return $masterProductCategory->id;
    }

    public function handle(MasterProductCategory $masterProductCategory): void
    {


        if ($masterProductCategory->type == MasterProductCategoryTypeEnum::FAMILY) {
            return;
        }
        $stats = [
            'number_master_product_categories_type_family' =>  DB::table('master_product_categories')
                ->where(function ($query) use ($masterProductCategory) {

                    if ($masterProductCategory->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
                        $query->where('master_department_id', $masterProductCategory->id);
                    } elseif ($masterProductCategory->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
                        $query->where('master_sub_department_id', $masterProductCategory->id);
                    }
                })
                ->where('type', MasterProductCategoryTypeEnum::FAMILY->value)
                ->where('deleted_at', null)
                ->count(),
            'number_current_master_product_categories_type_family' =>  DB::table('master_product_categories')
                ->where(function ($query) use ($masterProductCategory) {
                    if ($masterProductCategory->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
                        $query->where('master_department_id', $masterProductCategory->id);
                    } elseif ($masterProductCategory->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
                        $query->where('master_sub_department_id', $masterProductCategory->id);
                    }
                })
                ->where('status', true)
                ->where('type', MasterProductCategoryTypeEnum::FAMILY->value)
                ->where('deleted_at', null)
                ->count(),
        ];


        $masterProductCategory->stats()->update($stats);
    }

    public function getCommandSignature(): string
    {
        return 'master_product_categories:hydrate_master_families';

    }

    public function asCommand(): void
    {
        $masterProductCategories = MasterProductCategory::where('slug', 'sd-a')->first();
        $this->handle($masterProductCategories);

    }


}
