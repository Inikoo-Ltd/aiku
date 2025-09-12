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

class StoreDepartmentFromMasterDepartment extends GrpAction
{
    /**
     * @throws \Throwable
     */
    public function handle(MasterProductCategory $masterDepartment, array $modelData)
    {
        $activeShops = $masterDepartment->masterShop->shops()->where('state', ShopStateEnum::OPEN)->get();

        if ($activeShops) {
            foreach ($activeShops as $shop) {
                if (isset($modelData['shop_department']) && !array_key_exists($shop->id, $modelData['shop_department'])) {
                    continue;
                }

                $shopProductData = isset($modelData['shop_department'][$shop->id]) ? $modelData['shop_department'][$shop->id] : [];
                $createWebpage = isset($shopProductData['create_webpage']) ? $shopProductData['create_webpage'] : true;

                $data = [
                    'code' => $masterDepartment->code,
                    'name' => $masterDepartment->name,
                    'description' => $masterDepartment->description,
                    'state' => $createWebpage ? ProductCategoryStateEnum::ACTIVE : ProductCategoryStateEnum::IN_PROCESS,
                    'type' => ProductCategoryTypeEnum::DEPARTMENT,
                    'master_product_category_id' => $masterDepartment->id,
                ];

                $department = StoreProductCategory::run($shop, $data);
                $department->refresh();

                if ($createWebpage) {
                    $webpage = StoreProductCategoryWebpage::run($department);
                    PublishWebpage::make()->action($webpage, [
                        'comment' => 'first publish'
                    ]);
                }

                CloneProductCategoryImagesFromMaster::run($department);
            }
        }
    }

    public function rules(): array
    {
        return [
            'shop_department'            => ['sometimes', 'array']
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
