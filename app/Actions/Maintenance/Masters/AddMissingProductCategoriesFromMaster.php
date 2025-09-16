<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 Sept 2025 16:32:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters;

use App\Actions\Catalogue\CloneCatalogueStructure;
use App\Actions\Catalogue\ProductCategory\CloneProductCategoryImagesFromMaster;
use App\Actions\Catalogue\ProductCategory\StoreProductCategory;
use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AddMissingProductCategoriesFromMaster
{
    use asAction;

    /**
     * @throws \Throwable
     */
    public function handle(MasterShop $masterShop): void
    {
        /** @var MasterProductCategory $masterFamily */
        foreach ($masterShop->masterProductCategories()->where('type', MasterProductCategoryTypeEnum::FAMILY)->get() as $masterFamily) {
            if ($masterFamily->status) {
                foreach ($masterShop->shops as $shop) {
                    $this->upsertFamily($shop, $masterFamily);
                }
            }
        }
    }

    /**
     * @throws \Throwable
     */
    public function upsertFamily(Shop $shop, MasterProductCategory $masterFamily): ?ProductCategory
    {
        $code = $masterFamily->code;


        $foundFamilyData = DB::table('product_categories')
            ->where('shop_id', $shop->id)
            ->where('type', ProductCategoryTypeEnum::FAMILY->value)
            ->whereRaw("lower(code) = lower(?)", [$code])->first();

        $foundFamily = null;


        if (!$foundFamilyData) {
            $parent = $this->getParent($shop, $masterFamily);
            if ($parent) {
                print "creating family: $code\n";

                $foundFamily = StoreProductCategory::make()->action(
                    $parent,
                    [
                        'code'                       => $masterFamily->code,
                        'name'                       => $masterFamily->name,
                        'description'                => $masterFamily->description,
                        'type'                       => ProductCategoryTypeEnum::FAMILY,
                        'master_product_category_id' => $masterFamily->id
                    ]
                );
                CloneProductCategoryImagesFromMaster::run($foundFamily);
            }
        } else {
            $foundFamily = ProductCategory::find($foundFamilyData->id);

            if($foundFamily) {
                $dataToUpdate = [
                    //    'code' => $masterFamily->code,
                    //    'name' => $masterFamily->name,
                    'master_product_category_id' => $masterFamily->id
                ];
                if ($masterFamily->description && !$foundFamily->description) {
                    data_set($dataToUpdate, 'description', $masterFamily->description);
                }

                $foundFamily = UpdateProductCategory::make()->action(
                    $foundFamily,
                    $dataToUpdate
                );
            }
        }


        return $foundFamily;
    }


    /**
     * @throws \Throwable
     */
    public function getParent(Shop $shop, MasterProductCategory $masterFamily): ?ProductCategory
    {
        $parent = null;
        if ($masterFamily->masterDepartment) {
            $department = CloneCatalogueStructure::make()->upsertDepartment($shop, $masterFamily->masterDepartment);

            if($department) {
                if ($masterFamily->masterSubDepartment) {
                    $parent = CloneCatalogueStructure::make()->upsertSubDepartment($department, $masterFamily->masterSubDepartment);
                } else {
                    $parent = $department;
                }
            }
        }


        return $parent;
    }


    public function getCommandSignature(): string
    {
        return 'repair:add_missing_product_categories_from_master  {master}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $masterShop = MasterShop::where('slug', $command->argument('master'))->firstOrFail();

        $this->handle($masterShop);

        return 0;
    }


}
