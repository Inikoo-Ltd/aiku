<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Aug 2025 06:45:53 Central European Summer Time, Torremolinos, Spain
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters;

use App\Actions\Masters\MasterProductCategory\StoreMasterProductCategory;
use App\Actions\Masters\MasterProductCategory\UpdateMasterProductCategory;
use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\Log;

class AddMissingFamiliesToMaster
{
    use asAction;

    /**
     * @throws \Throwable
     */
    public function handle(Shop $fromShop, MasterShop $shop, bool $missingOnly = false): void
    {
        $categoriesToAdd = $fromShop->productCategories()->where('product_categories.type', MasterProductCategoryTypeEnum::FAMILY)
            ->where('product_categories.state', ProductCategoryStateEnum::ACTIVE);
        if($missingOnly){
            $categoriesToAdd->leftJoin('master_product_categories as mpc', 'mpc.id', '=', 'product_categories.master_product_category_id')
                ->whereNull('mpc.id')
                ->select(
                    'product_categories.*',
                    DB::raw('mpc.id IS NULL as no_master')
            );
        }
        $categoriesToAdd = $categoriesToAdd->get();

        foreach ($categoriesToAdd as $categoryToAdd) {
            // Create/Find Master Family using Family Data
            $masterFamily = $this->upsertMasterFamily($shop, $categoryToAdd);
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

        if($family->no_master){
            // Link Product Category with Master
            UpdateProductCategory::make()->action(
                $family,
                ['master_product_category_id' => $foundMasterFamily->id]
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
        // --missing to search for family with no master
        return 'repair:add_missing_families_to_master {from?} {to?} {--missing}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        if($command->argument('to') && $command->argument('from')){
            $fromShop = Shop::where('slug', $command->argument('from'))->firstOrFail();
            $toShop   = MasterShop::where('slug', $command->argument('to'))->firstOrFail();
    
            $this->handle($fromShop, $toShop, $command->option('missing'));
        }else{
            $shops = Shop::whereNotNull('master_shop_id')->get();
            foreach($shops as $shop){
                $this->handle($shop, $shop->masterShop, $command->option('missing'));
            }
        }

        return 0;
    }


}
