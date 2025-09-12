<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:05:20 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Catalogue\ProductCategory\CloneProductCategoryImagesFromMaster;
use App\Actions\Catalogue\ProductCategory\StoreProductCategory;
use App\Actions\Catalogue\ProductCategory\StoreProductCategoryWebpage;
use App\Actions\GrpAction;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterProductCategory;

class StoreFamilyFromMasterFamily extends GrpAction
{
    /**
     * @throws \Throwable
     */
    public function handle(MasterProductCategory $masterFamily, array $modelData)
    {
        $activeShops = $masterFamily->masterShop->shops()->where('state', ShopStateEnum::OPEN)->get();

        if ($activeShops) {
            foreach ($activeShops as $shop) {
                if (isset($modelData['shop_family']) && !array_key_exists($shop->id, $modelData['shop_family'])) {
                    continue;
                }

                $shopProductData = isset($modelData['shop_family'][$shop->id]) ? $modelData['shop_family'][$shop->id] : [];
                $createWebpage = isset($shopProductData['create_webpage']) ? $shopProductData['create_webpage'] : true;

                $subDepartment = null;
                $department = null;

                if ($masterFamily->masterSubDepartment) {
                    $subDepartment = $masterFamily->masterSubDepartment->productCategories()->where('shop_id', $shop->id)->first();
                }

                if ($masterFamily->masterDepartment) {
                    $department = $masterFamily->masterDepartment->productCategories()->where('shop_id', $shop->id)->first();
                }

                $data = [
                    'code' => $masterFamily->code,
                    'name' => $masterFamily->name,
                    'description' => $masterFamily->description,
                    'state' => $createWebpage ? ProductCategoryStateEnum::ACTIVE : ProductCategoryStateEnum::IN_PROCESS,
                    'type' => ProductCategoryTypeEnum::FAMILY,
                    'master_product_category_id' => $masterFamily->id,
                ];
                if ($subDepartment) {
                    $family = StoreProductCategory::run($subDepartment, $data);
                } elseif ($department) {
                    $family = StoreProductCategory::run($department, $data);
                } else {
                    $family = StoreProductCategory::run($shop, $data);
                }
                $family->refresh();
                
                if ($createWebpage) {
                    $webpage = StoreProductCategoryWebpage::run($family);
                    PublishWebpage::make()->action($webpage, [
                        'comment' => 'first publish'
                    ]);
                }

                CloneProductCategoryImagesFromMaster::run($family);
            }
        }
    }

    public function rules(): array
    {
        return [
            'shop_family'            => ['sometimes', 'array']
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(MasterProductCategory $masterProductCategory, array $modelData, int $hydratorsDelay = 0, $strict = true, $audit = true)
    {
        if (!$audit) {
            Product::disableAuditing();
        }

        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->strict         = $strict;

        $group = $masterProductCategory->group;

        $this->initialisation($group, $modelData);

        return $this->handle($masterProductCategory, $this->validatedData);
    }

}
