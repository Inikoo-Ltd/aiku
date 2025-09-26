<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Aug 2025 06:45:53 Central European Summer Time, Torremolinos, Spain
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters;

use App\Actions\Masters\MasterProductCategory\StoreMasterProductCategory;
use App\Actions\Masters\MasterProductCategory\UpdateMasterProductCategory;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AddMissingFamiliesToMaster
{
    use asAction;

    /**
     * @throws \Throwable
     */
    public function handle(Shop $fromShop, MasterShop $shop): void
    {
        $categoriesToAdd = $fromShop->productCategories()->where('type', MasterProductCategoryTypeEnum::FAMILY)
            ->where('state', ProductCategoryStateEnum::ACTIVE)
            ->get();

        foreach ($categoriesToAdd as $categoryToAdd) {
            $this->upsertMasterFamily($shop, $categoryToAdd);
        }
    }

    /**
     * @throws \Throwable
     */
    public function upsertMasterFamily(MasterShop $masterShop, ProductCategory $family): ?MasterProductCategory
    {
        $code = $family->code;

        $foundMasterFamilyData = DB::table('master_product_categories')
            ->where('master_shop_id', $masterShop->id)
            ->where('type', MasterProductCategoryTypeEnum::FAMILY->value)
            ->where('deleted_at', null)
            ->whereRaw("lower(code) = lower(?)", [$code])->first();


        if (!$foundMasterFamilyData) {
            $masterParent = $this->getMasterParent($masterShop, $family);

            if (!$masterParent) {
                $masterParent = $masterShop;
            }


            $foundMasterFamily = StoreMasterProductCategory::make()->action(
                parent: $masterParent,
                modelData: [
                    'code'        => $family->code,
                    'name'        => $family->name,
                    'description' => $family->description,
                    'type'        => MasterProductCategoryTypeEnum::FAMILY,
                ],
                createChildren: false
            );
        } else {
            $foundMasterFamily = MasterProductCategory::find($foundMasterFamilyData->id);

            $dataToUpdate = [
                'code' => $family->code,
                'name' => $family->name,
            ];

            if ($family->description && !$foundMasterFamily->description) {
                data_set($dataToUpdate, 'description', $family->description);
            }

            $foundMasterFamily = UpdateMasterProductCategory::make()->action(
                $foundMasterFamily,
                $dataToUpdate
            );
        }

        if ($foundMasterFamily) {
            $markForDiscontinued = false;
            $status              = true;
            $discontinuingAt     = null;
            $discontinuedAt      = null;


            if ($family->state == ProductCategoryStateEnum::DISCONTINUED) {
                $status          = false;
                $discontinuedAt  = $family->discontinued_at;
                $discontinuingAt = $family->discontinuing_at;
            }

            if ($family->state == ProductCategoryStateEnum::DISCONTINUING) {
                $markForDiscontinued = true;
                $discontinuingAt     = $family->discontinuing_at;
            }

            UpdateMasterProductCategory::run(
                $foundMasterFamily,
                [
                    'status'                   => $status,
                    'mark_for_discontinued'    => $markForDiscontinued,
                    'mark_for_discontinued_at' => $discontinuingAt,
                    'discontinued_at'          => $discontinuedAt,
                ]
            );
        }

        return $foundMasterFamily;
    }


    /**
     * @throws \Throwable
     */
    public function getMasterParent(MasterShop $masterShop, ProductCategory $family): ?MasterProductCategory
    {
        if ($family->subDepartment) {
            $code                         = $family->subDepartment->code;
            $foundMasterSubDepartmentData = DB::table('master_product_categories')
                ->where('master_shop_id', $masterShop->id)
                ->where('type', MasterProductCategoryTypeEnum::SUB_DEPARTMENT->value)
                ->where('deleted_at', null)
                ->whereRaw("lower(code) = lower(?)", [$code])->first();
            if ($foundMasterSubDepartmentData) {
                $masterSubDepartment = MasterProductCategory::find($foundMasterSubDepartmentData->id);
                if ($masterSubDepartment) {
                    return $masterSubDepartment;
                }
            }
        }


        if ($family->department) {
            $code                      = $family->department->code;
            $foundMasterDepartmentData = DB::table('master_product_categories')
                ->where('master_shop_id', $masterShop->id)
                ->where('type', MasterProductCategoryTypeEnum::DEPARTMENT->value)
                ->where('deleted_at', null)
                ->whereRaw("lower(code) = lower(?)", [$code])->first();
            if ($foundMasterDepartmentData) {
                $masterDepartment = MasterProductCategory::find($foundMasterDepartmentData->id);
                if ($masterDepartment) {
                    return $masterDepartment;
                }
            }
        }


        return null;
    }

    public function getCommandSignature(): string
    {
        return 'repair:add_missing_families_to_master {from} {to}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $toShop   = MasterShop::where('slug', $command->argument('to'))->firstOrFail();
        $fromShop = Shop::where('slug', $command->argument('from'))->firstOrFail();

        $this->handle($fromShop, $toShop);

        return 0;
    }


}
