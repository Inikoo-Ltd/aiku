<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Jun 2024 00:42:11 Central European Summer Time, Abu Dhabi Airport
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\ProductCategory\Hydrators\FamilyHydrateBestSellerProduct;
use App\Actions\Catalogue\ProductCategory\Hydrators\FamilyHydrateProducts;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateSales;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Catalogue\ProductCategory;

class HydrateFamilies
{
    use WithHydrateCommand;
    public string $commandSignature = 'hydrate:families {organisations?*} {--S|shop= shop slug} {--s|slugs=} ';

    public function __construct()
    {
        $this->model = ProductCategory::class;
        $this->restriction = 'family';
    }

    public function handle(ProductCategory $productCategory): void
    {
        FamilyHydrateProducts::run($productCategory);
        ProductCategoryHydrateSales::run($productCategory);
        FamilyHydrateBestSellerProduct::run($productCategory);
    }

}
