<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Feb 2026 14:18:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\CloneCatalogueStructure;
use App\Actions\Catalogue\CloneCollections;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateDepartments;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateMasterAssets;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateMasterSubDepartments;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterFamilyHydrateFamilies;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterFamilyHydrateMasterAssets;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterFamilyHydrateStatus;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterProductCategoryHydrateMasterFamilies;
use App\Actions\Masters\MasterProductCategory\UpdateMasterProductCategoryWebImages;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateCatalogueDuringMigration
{

    use AsAction;

    public function handle(MasterShop $masterShop, Command $command): void
    {
        $primaryPivotShop = $masterShop->shops()->where('migration_pivot', 1)->firstOrFail();
        if ($primaryPivotShop) {
            $command->info('Updating master catalogue from pivot shop '.$primaryPivotShop->name);
            CloneCatalogueStructure::run(
                fromShop: $primaryPivotShop,
                shop: $masterShop,
                skipDepartments: true
            );
            $command->info('Hydrating master categories');

            foreach ($masterShop->masterProductCategories as $masterProductCategory) {
                MasterProductCategoryHydrateMasterFamilies::run($masterProductCategory);
                MasterDepartmentHydrateMasterAssets::run($masterProductCategory);
                MasterDepartmentHydrateDepartments::run($masterProductCategory);
                MasterDepartmentHydrateMasterSubDepartments::run($masterProductCategory);
                MasterFamilyHydrateMasterAssets::run($masterProductCategory);
                MasterFamilyHydrateFamilies::run($masterProductCategory);
                MasterFamilyHydrateStatus::run($masterProductCategory);
                UpdateMasterProductCategoryWebImages::run($masterProductCategory);
            }
        }

        foreach ($masterShop->shops as $shop) {
            if ($shop->state == ShopStateEnum::CLOSED) {
                continue;
            }
            $command->info('Updating shop catalogue from master to shop '.$shop->name);

            if ($shop->id != $primaryPivotShop->id) {
                CloneCatalogueStructure::run(
                    fromShop: $masterShop,
                    shop: $shop,
                    deleteMissing: true
                );
            } else {
                CloneCatalogueStructure::run(
                    fromShop: $masterShop,
                    shop: $shop,
                    deleteMissing: true,
                    skipProducts: true,
                    skipFamilies: true

                );
            }
            CloneCollections::run(
                fromShop: $masterShop,
                shop: $shop,
                deleteMissing: true
            );
            if(!in_array($shop->slug, ['bg', 'ua'])){
                UpdateProductDescriptionAndNameFromAurora::run($shop);
                UpdateFamilyDescriptionAndNameFromAurora::run($shop);
            }
        }
    }

    public function getCommandSignature(): string
    {
        return 'repair:update_catalogue_during_migration {masterShop}';
    }

    public function asCommand(Command $command): int
    {
        $masterShop = MasterShop::where('slug', $command->argument('masterShop'))->firstOrFail();
        $this->handle($masterShop, $command);

        return 0;
    }

}