<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Sept 2024 18:05:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateForSale;
use App\Actions\Catalogue\ProductCategory\Hydrators\DepartmentHydrateProducts;
use App\Actions\Catalogue\ProductCategory\Hydrators\FamilyHydrateProducts;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateProducts;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateProducts;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateProducts;
use App\Models\Catalogue\Product;

trait WithProductHydrators
{
    protected function productHydrators(Product $product): void
    {
        ProductHydrateForSale::run($product);
        GroupHydrateProducts::dispatch($product->group)->delay($this->hydratorsDelay);
        OrganisationHydrateProducts::dispatch($product->organisation)->delay($this->hydratorsDelay);
        ShopHydrateProducts::dispatch($product->shop)->delay($this->hydratorsDelay);
        if ($product->department_id) {
            DepartmentHydrateProducts::dispatch($product->department)->delay($this->hydratorsDelay);
        }
        if ($product->family_id) {
            FamilyHydrateProducts::dispatch($product->family)->delay($this->hydratorsDelay);
        }
    }
}
