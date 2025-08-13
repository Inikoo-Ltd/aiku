<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 Aug 2025 10:30:25 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterDepartmentHydrateMasterSubDepartments implements ShouldBeUnique
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
            'number_master_product_categories_type_sub_department' => DB::table('master_product_categories')
                ->where('master_department_id', $masterDepartment->id)
                ->where('type', MasterProductCategoryTypeEnum::SUB_DEPARTMENT)
                ->count(),
            'number_current_master_product_categories_type_sub_department' => DB::table('master_product_categories')
                ->where('master_department_id', $masterDepartment->id)
                ->where('status', true)
                ->where('type', MasterProductCategoryTypeEnum::SUB_DEPARTMENT)
                ->count(),
        ];

        $masterDepartment->stats()->update($stats);
    }


}
