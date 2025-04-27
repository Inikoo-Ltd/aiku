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
use Lorisleiva\Actions\Concerns\AsAction;

class MasterDepartmentHydrateMasterFamilies implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(MasterProductCategory $masterDepartment): string
    {
        return $masterDepartment->id;
    }

    public function handle(MasterProductCategory $masterDepartment): void
    {

        if ($masterDepartment->type == MasterProductCategoryTypeEnum::FAMILY) {
            return;
        }

        $stats = [
            'number_master_product_categories_type_family' => $masterDepartment->masterFamilies()->count(),
            'number_current_master_product_categories_type_family' => $masterDepartment->masterFamilies()->where('status', true)->count(),
        ];



        $masterDepartment->stats()->update($stats);
    }


}
