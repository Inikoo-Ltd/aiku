<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Masters\MasterProductCategory\WithMasterFamiliesFromParentCollections;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterDepartmentFamilies
{
    use AsObject;
    use WithMasterFamiliesFromParentCollections;

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

    /**
     * Master families of other departments brought in by the active master collections attached to the department.
     * The website lists them after the department own families, in the order of their own department.
     *
     * @return Collection<int, MasterProductCategory>
     */
    public function getCollectionFamilies(MasterProductCategory $masterDepartment): Collection
    {
        return MasterProductCategory::query()
            ->select('master_product_categories.*')
            ->selectSub($this->parentMasterCollectionNamesOfFamily($masterDepartment->id), 'collection_names')
            ->where('master_product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->where('master_product_categories.status', true)
            ->where('master_product_categories.master_shop_id', $masterDepartment->master_shop_id)
            ->where(function ($query) use ($masterDepartment) {
                $query->whereNull('master_product_categories.master_department_id')
                    ->orWhere('master_product_categories.master_department_id', '!=', $masterDepartment->id);
            })
            ->whereIn('master_product_categories.id', $this->masterFamilyIdsFromParentCollections($masterDepartment->id))
            ->orderByRaw('master_product_categories.website_position ASC NULLS LAST')
            ->orderByRaw('master_product_categories.created_at DESC')
            ->get();
    }
}
