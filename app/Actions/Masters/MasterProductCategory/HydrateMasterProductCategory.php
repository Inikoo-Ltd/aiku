<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Apr 2025 15:09:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\HydrateModel;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateDepartments;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateMasterAssets;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateMasterFamilies;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterFamilyHydrateFamilies;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterFamilyHydrateMasterAssets;
use App\Actions\Traits\WithNormalise;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class HydrateMasterProductCategory extends HydrateModel
{
    use WithNormalise;

    public string $commandSignature = 'hydrate:master_product_categories';


    public function handle(MasterProductCategory $masterProductCategory): void
    {
        MasterDepartmentHydrateMasterFamilies::run($masterProductCategory);
        MasterDepartmentHydrateMasterAssets::run($masterProductCategory);
        MasterDepartmentHydrateDepartments::run($masterProductCategory);

        MasterFamilyHydrateMasterAssets::run($masterProductCategory);
        MasterFamilyHydrateFamilies::run($masterProductCategory);
    }


    public function asCommand(Command $command): int
    {
        $command->info("Hydrating Master Product categories");
        $count = MasterProductCategory::count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        MasterProductCategory::chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");


        return 0;
    }
}
