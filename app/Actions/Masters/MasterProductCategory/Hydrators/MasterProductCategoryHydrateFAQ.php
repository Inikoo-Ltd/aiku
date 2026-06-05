<?php

/*
 * author Louis Perez
 * created on 05-06-2026-15h-32m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterProductCategory\Hydrators;

use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\GrpAction;
use App\Models\Masters\MasterProductCategory;

class MasterProductCategoryHydrateFAQ extends GrpAction
{
    public function handle(MasterProductCategory $masterProductCategory)
    {
        foreach ($masterProductCategory->productCategories as $productCategory) {
            UpdateProductCategory::make()->action($productCategory, [
                'faq' => $masterProductCategory->faq,
            ]);
        }
    }

    public function action(MasterProductCategory $masterProductCategory)
    {
        $this->initialisation($masterProductCategory->group, []);
        
        $this->handle($masterProductCategory);
    }
}
