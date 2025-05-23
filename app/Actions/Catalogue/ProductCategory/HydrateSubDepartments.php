<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Mar 2025 20:35:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateFamilies;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateSales;
use App\Actions\Catalogue\ProductCategory\Hydrators\SubDepartmentHydrateProducts;
use App\Actions\Catalogue\ProductCategory\Hydrators\SubDepartmentHydrateSubDepartments;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Catalogue\ProductCategory;

class HydrateSubDepartments
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:sub_departments {organisations?*} {--S|shop= shop slug}  {--s|slugs=} ';

    public function __construct()
    {
        $this->model       = ProductCategory::class;
        $this->restriction = 'sub_department';
    }

    public function handle(ProductCategory $productCategory): void
    {
        SubDepartmentHydrateSubDepartments::run($productCategory);
        SubDepartmentHydrateProducts::run($productCategory);
        ProductCategoryHydrateFamilies::run($productCategory);
        ProductCategoryHydrateSales::run($productCategory);
    }

}
