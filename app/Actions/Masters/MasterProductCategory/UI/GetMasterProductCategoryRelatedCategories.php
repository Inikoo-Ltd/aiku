<?php

/*
 * author Louis Perez
 * created on 29-05-2026-13h-07m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\OrgAction;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Facades\DB;

class GetMasterProductCategoryRelatedCategories extends OrgAction
{
    public function handle(MasterProductCategory $masterProductCategory): \Illuminate\Support\Collection
    {
        return DB::table('master_product_category_has_related_product_categories')
            ->leftJoin('master_product_categories', 'related_master_product_category_id', 'master_product_categories.id')
            ->where('master_product_category_id', $masterProductCategory->id)
            ->orderBy('position')->get();
    }
}
