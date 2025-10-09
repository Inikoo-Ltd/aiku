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
use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\GrpAction;
use App\Actions\Helpers\Translations\TranslateCategoryModel;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Arr;

class StoreFamilyFromMasterFamily extends GrpAction
{
    /**
     * @throws \Throwable
     */
    public function handle(MasterProductCategory $masterFamily, array $modelData): void
    {
        $activeShops = $masterFamily->masterShop->shops()->where('state', ShopStateEnum::OPEN)->get();

        if ($activeShops) {
            /** @var Shop $shop */
            foreach ($activeShops as $shop) {
                if (isset($modelData['shop_family']) && !array_key_exists($shop->id, $modelData['shop_family'])) {
                    continue;
                }

                $shopProductData = isset($modelData['shop_family'][$shop->id]) ? $modelData['shop_family'][$shop->id] : [];
                $createWebpage   = $shopProductData['create_webpage'] ?? true;

                $subDepartment = null;
                $department    = null;

                if ($masterFamily->masterSubDepartment) {
                    $subDepartment = $masterFamily->masterSubDepartment->productCategories()->where('shop_id', $shop->id)->first();
                }

                if ($masterFamily->masterDepartment) {
                    $department = $masterFamily->masterDepartment->productCategories()->where('shop_id', $shop->id)->first();
                }

                $data = [
                    'code'                       => $masterFamily->code,
                    'name'                       => $masterFamily->name,
                    'description'                => $masterFamily->description,
                    'description_title'          => $masterFamily->description_title,
                    'description_extra'          => $masterFamily->description_extra,
                    'state'                      => $createWebpage ? ProductCategoryStateEnum::ACTIVE : ProductCategoryStateEnum::IN_PROCESS,
                    'type'                       => ProductCategoryTypeEnum::FAMILY,
                    'master_product_category_id' => $masterFamily->id,
                ];


                $family = ProductCategory::where('shop_id', $shop->id)
                    ->whereRaw("lower(code) = lower(?)", [$masterFamily->code])
                    ->first();

                if ($family) {
                    if ($subDepartment) {
                        $data['parent_id'] = $subDepartment->id;
                        $data['sub_department_id'] = $subDepartment->id;
                        $data['department_id'] = $subDepartment->department_id;
                    } elseif ($department) {
                        $data['parent_id'] = $department->id;
                        $data['department_id'] = $department->id;
                        $data['sub_department_id'] = null;
                    }
                    $this->updateFoundFamily($family, $data, $createWebpage);
                    continue;
                }

                if ($subDepartment) {
                    $family = StoreProductCategory::run($subDepartment, $data);
                } elseif ($department) {
                    $family = StoreProductCategory::run($department, $data);
                } else {
                    throw new \Exception('No department or sub department found');
                }
                $family->refresh();

                if ($createWebpage) {
                    $webpage = StoreProductCategoryWebpage::run($family);
                    PublishWebpage::make()->action($webpage, [
                        'comment' => 'first publish'
                    ]);
                }

                CloneProductCategoryImagesFromMaster::run($family);
                TranslateCategoryModel::dispatch(
                    $family,
                    Arr::only($data, ['name', 'description', 'description_title', 'description_extra'])
                );
            }
        }
    }

    public function updateFoundFamily(ProductCategory $family, array $modelData, bool $createWebpage): void
    {
        $family = UpdateProductCategory::run($family, $modelData);
        CloneProductCategoryImagesFromMaster::run($family);
        $family->refresh();
        if ($createWebpage && $family->webpage === null) {
            $webpage = StoreProductCategoryWebpage::run($family);
            PublishWebpage::make()->action($webpage, [
                'comment' => 'first publish'
            ]);
        }
        TranslateCategoryModel::dispatch(
            $family,
            Arr::only($modelData, ['name', 'description', 'description_title', 'description_extra'])
        );
    }

    public function rules(): array
    {
        return [
            'shop_family' => ['sometimes', 'array']
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(MasterProductCategory $masterProductCategory, array $modelData, int $hydratorsDelay = 0, $strict = true, $audit = true): void
    {
        if (!$audit) {
            Product::disableAuditing();
        }

        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->strict         = $strict;

        $group = $masterProductCategory->group;

        $this->initialisation($group, $modelData);

        $this->handle($masterProductCategory, $this->validatedData);
    }

}
