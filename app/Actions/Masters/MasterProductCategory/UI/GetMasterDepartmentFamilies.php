<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterDepartmentFamilies
{
    use AsObject;

    /**
     * Every master family hanging off the department, the ones under its sub departments included,
     * in the order the website family blocks will show them.
     *
     * @return Collection<int, MasterProductCategory>
     */
    public function handle(MasterProductCategory $masterDepartment): Collection
    {
        return MasterProductCategory::where('master_department_id', $masterDepartment->id)
            ->where('type', ProductCategoryTypeEnum::FAMILY)
            ->where('status', true)
            ->orderByRaw('website_position ASC NULLS LAST')
            ->orderByRaw('created_at DESC')
            ->get();
    }
}
