<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Jun 2024 00:29:36 Central European Summer Time, Abu Dhabi Airport
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\ProductCategory\Hydrators\DepartmentHydrateBestFamilySeller;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateCollections;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateFamilies;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateSales;
use App\Actions\Catalogue\ProductCategory\Hydrators\DepartmentHydrateProducts;
use App\Actions\Catalogue\ProductCategory\Hydrators\DepartmentHydrateSubDepartments;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Catalogue\ProductCategory;

class HydrateDepartments
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:departments {organisations?*} {--S|shop= shop slug}  {--s|slugs=} ';

    public function __construct()
    {
        $this->model       = ProductCategory::class;
        $this->restriction = 'department';
    }

    public function handle(ProductCategory $productCategory): void
    {
        DepartmentHydrateSubDepartments::run($productCategory);
        DepartmentHydrateProducts::run($productCategory);
        ProductCategoryHydrateFamilies::run($productCategory);
        ProductCategoryHydrateSales::run($productCategory);
        ProductCategoryHydrateCollections::run($productCategory);
        DepartmentHydrateBestFamilySeller::run($productCategory);
    }

}
