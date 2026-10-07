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
use App\Actions\Catalogue\ProductCategory\Hydrators\SubDepartmentHydrateProducts;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateProducts;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateProductsNotOnline;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateProductsWithDuplicatedBarcode;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateProductsWithMismatchFamily;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateProductsWithNoDescription;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateProductsWithNoImage;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateProducts;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterFamilyHydrateProducts;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateProducts;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;

trait WithProductHydrators
{
    protected function productHydrators(Product $product, $hydrateForSale = true): void
    {
        if ($hydrateForSale) {
            ProductHydrateForSale::run($product);
        }
        GroupHydrateProducts::dispatch($product->group)->delay($this->hydratorsDelay);
        OrganisationHydrateProducts::dispatch($product->organisation)->delay($this->hydratorsDelay);
        ShopHydrateProducts::dispatch($product->shop)->delay($this->hydratorsDelay);
        ShopHydrateProductsWithNoImage::dispatch($product->shop)->delay($this->hydratorsDelay);
        ShopHydrateProductsWithNoDescription::dispatch($product->shop)->delay($this->hydratorsDelay);
        ShopHydrateProductsWithMismatchFamily::dispatch($product->shop)->delay($this->hydratorsDelay);
        ShopHydrateProductsNotOnline::dispatch($product->shop)->delay($this->hydratorsDelay);
        ShopHydrateProductsWithDuplicatedBarcode::dispatch($product->shop)->delay($this->hydratorsDelay);
        if ($product->department_id) {
            DepartmentHydrateProducts::dispatch($product->department_id)->delay(2);
        }
        if ($product->family) {
            FamilyHydrateProducts::dispatch($product->family)->delay($this->hydratorsDelay);
        }
        if ($product->sub_department_id) {
            SubDepartmentHydrateProducts::dispatch($product->sub_department_id)->delay(2);
        }
        if ($product->master_product_id) {
            MasterFamilyHydrateProducts::dispatch(MasterAsset::whereKey($product->master_product_id)->value('master_family_id'))->delay($this->hydratorsDelay);
        }
    }
}
