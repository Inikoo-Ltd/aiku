<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Jul 2025 16:55:04 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue;

use App\Actions\Catalogue\ProductCategory\AttachFamiliesToDepartment;
use App\Actions\Catalogue\ProductCategory\AttachFamiliesToSubDepartment;
use App\Actions\Catalogue\ProductCategory\StoreProductCategory;
use App\Actions\Catalogue\ProductCategory\StoreSubDepartment;
use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneCatalogueStructure
{
    use asAction;

    /**
     * @throws \Throwable
     */
    public function handle(MasterShop|Shop $fromShop, Shop $shop): void
    {
        $this->cloneDepartments($fromShop, $shop);
        $this->cloneSubDepartments($fromShop, $shop);
    }


    /**
     * @throws \Throwable
     */
    public function cloneDepartments(MasterShop|Shop $fromShop, Shop $shop): void
    {
        /** @var ProductCategory|MasterProductCategory $fromDepartment */
        foreach ($fromShop->departments() as $fromDepartment) {
            $department = $this->upsertDepartment($shop, $fromDepartment);

            if ($fromDepartment instanceof MasterProductCategory) {
                $fromFamiliesAttachedToDepartment = DB::table('product_categories')
                    ->where('type', ProductCategoryTypeEnum::FAMILY->value)
                    ->where('parent_id', $fromDepartment->id)->get();

                foreach ($fromFamiliesAttachedToDepartment as $fromFamilyData) {
                    $fromFamily = ProductCategory::find($fromFamilyData->id);
                    $this->cloneFamiliesParentRelationship($department, $fromFamily);
                }
            }
        }
    }

    /**
     * @throws \Throwable
     */
    public function upsertDepartment(Shop $shop, ProductCategory|MasterProductCategory $department): ProductCategory
    {
        $code = $department->code;

        $foundDepartmentData = DB::table('product_categories')
            ->where('shop_id', $shop->id)
            ->where('type', ProductCategoryTypeEnum::DEPARTMENT->value)
            ->whereRaw("lower(code) = lower(?)", [$code])->first();


        if (!$foundDepartmentData) {
            $foundDepartment = StoreProductCategory::make()->action(
                $shop,
                [
                    'code'        => $department->code,
                    'name'        => $department->name,
                    'description' => $department->description,
                    'type'        => ProductCategoryTypeEnum::DEPARTMENT
                ]
            );
        } else {
            $foundDepartment = ProductCategory::find($foundDepartmentData->id);

            $dataToUpdate = [
                'code' => $department->code,
                'name' => $department->name,
            ];
            if ($department->description) {
                data_set($dataToUpdate, 'description', $department->description);
            }

            $foundDepartment = UpdateProductCategory::make()->action(
                $foundDepartment,
                $dataToUpdate
            );
        }

        return $foundDepartment;
    }

    /**
     * @throws \Throwable
     */
    public function upsertSubDepartment(ProductCategory $department, ProductCategory|MasterProductCategory $subDepartment): ProductCategory
    {
        $code                   = $subDepartment->code;
        $foundSubDepartmentData = DB::table('product_categories')
            ->where('shop_id', $department->shop->id)
            ->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT->value)
            ->whereRaw("lower(code) = lower(?)", [$code])->first();


        if (!$foundSubDepartmentData) {
            /** @var ProductCategory $department */
            $department = $this->upsertDepartment($department->shop, $subDepartment->parent);


            $subDepartment = StoreSubDepartment::make()->action(
                $department,
                [
                    'code'        => $subDepartment->code,
                    'name'        => $subDepartment->name,
                    'description' => $subDepartment->description,
                ]
            );
        } else {
            $foundSubDepartment = ProductCategory::find($foundSubDepartmentData->id);
            $dataToUpdate       = [
                'code' => $subDepartment->code,
                'name' => $subDepartment->name,
            ];
            if ($subDepartment->description) {
                data_set($dataToUpdate, 'description', $subDepartment->description);
            }
            $subDepartment = UpdateProductCategory::make()->action(
                $foundSubDepartment,
                $dataToUpdate
            );
        }

        return $subDepartment;
    }

    /**
     * @throws \Throwable
     */
    public function cloneSubDepartments(MasterShop|Shop $fromShop, Shop $shop): void
    {
        /** @var ProductCategory|MasterProductCategory $fromSubDepartment */
        foreach ($fromShop->subDepartments() as $fromSubDepartment) {
            $department = $this->upsertDepartment($shop, $fromSubDepartment->parent);

            $subDepartment = $this->upsertSubDepartment($department, $fromSubDepartment);

            foreach ($fromSubDepartment->getFamilies() as $fromFamilyData) {
                $fromFamily = ProductCategory::find($fromFamilyData->id);
                $this->cloneFamiliesParentRelationship($subDepartment, $fromFamily);
            }
        }
    }

    public function cloneFamiliesParentRelationship(ProductCategory $parent, ProductCategory|MasterProductCategory $fromFamily): void
    {
        $foundFamilyData = DB::table('product_categories')
            ->where('shop_id', $parent->shop->id)
            ->where('type', ProductCategoryTypeEnum::FAMILY->value)
            ->whereRaw("lower(code) = lower(?)", [$fromFamily->code])->first();

        $familiesToAttach = [];
        if ($foundFamilyData) {
            $familiesToAttach[$foundFamilyData->id] = $foundFamilyData->id;
        }

        if (empty($familiesToAttach)) {
            return;
        }

        if ($parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            AttachFamiliesToSubDepartment::make()->action(
                $parent,
                [
                    'families_id' => $familiesToAttach
                ]
            );
        } elseif ($parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
            AttachFamiliesToDepartment::make()->action(
                $parent,
                [
                    'families' => $familiesToAttach
                ]
            );
        }
    }

    public function getCommandSignature(): string
    {
        return 'catalogue:clone {from_type} {from} {to}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $shop = Shop::where('slug', $command->argument('to'))->firstOrFail();
        if ($command->argument('from_type') == 'shop') {
            $fromShop = Shop::where('slug', $command->argument('from'))->firstOrFail();
        } else {
            $fromShop = MasterShop::where('slug', $command->argument('from'))->firstOrFail();
        }
        $this->handle($fromShop, $shop);

        return 0;
    }


}
