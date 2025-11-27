<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 21:46:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\OrgAction;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class ChangeMasterSubDepartmentParent extends OrgAction
{
    public function handle(MasterProductCategory $masterProductCategory, array $modelData): bool
    {
        $updatedCount = 0;
        if (Arr::has($modelData, 'sub_department_ids')) {
            $subDepartmentIds = Arr::pull($modelData, 'sub_department_ids');
            $updatedCount = MasterProductCategory::whereIn('id', $subDepartmentIds)
                ->where('type', MasterProductCategoryTypeEnum::SUB_DEPARTMENT)
                ->update([
                    'master_department_id' => $masterProductCategory->id,
                    'master_parent_id' => $masterProductCategory->id,
                ]);
        }
        return (bool) $updatedCount;
    }


    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): bool
    {
        return $this->handle($masterProductCategory, $request->all());
    }

}
