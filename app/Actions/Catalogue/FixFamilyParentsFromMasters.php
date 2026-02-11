<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Jul 2025 16:55:04 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue;

use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class FixFamilyParentsFromMasters
{
    use asAction;

    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, Command $command): void
    {
        $masterShop = $shop->masterShop;
        if ($masterShop) {
            $command->info("Fixing family parents from masters for shop $shop->name");


            $subDepartments = CloneCatalogueStructure::make()->getSubDepartments($shop);
            foreach ($subDepartments as $subDepartment) {

                $masterSubDepartment = $subDepartment->masterProductCategory;

                if ($masterSubDepartment) {
                    $fromMasterFamilies = CloneCatalogueStructure::make()->getCategories($masterSubDepartment, 'family');
                    foreach ($fromMasterFamilies as $masterFamily) {
                        if ($masterFamily->masterSubDepartment) {
                            $family = $masterFamily->productCategories()->where('shop_id', $shop->id)->first();
                            if ($family) {
                                CloneCatalogueStructure::make()->attachFamily($subDepartment, $family);
                            }
                        }
                    }
                }
            }
        }
    }


    public function getCommandSignature(): string
    {
        return 'fix:families_parents_from_masters {shop?}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        if ($command->argument('shop')) {
            $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();
            $this->handle($shop, $command);

            return 0;
        }

        foreach (Shop::all() as $shop) {
            $this->handle($shop, $command);
        }

        return 0;
    }


}
