<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Oct 2025 16:00:44 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection;

use App\Actions\HydrateModel;
use App\Actions\Masters\MasterCollection\Hydrators\MasterCollectionHydrateFamilies;
use App\Actions\Masters\MasterCollection\Hydrators\MasterCollectionHydrateMasterCollections;
use App\Actions\Masters\MasterCollection\Hydrators\MasterCollectionHydrateMasterProducts;
use App\Actions\Traits\WithNormalise;
use App\Models\Masters\MasterCollection;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class HydrateMasterCollection extends HydrateModel
{
    use WithNormalise;

    public string $commandSignature = 'hydrate:master_collections';


    public function handle(MasterCollection $masterCollection): void
    {
        MasterCollectionHydrateFamilies::run($masterCollection);
        MasterCollectionHydrateMasterCollections::run($masterCollection);
        MasterCollectionHydrateMasterProducts::run($masterCollection);
    }


    public function asCommand(Command $command): int
    {
        $command->info("Hydrating Master Collections");
        $count = MasterCollection::count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        MasterCollection::chunk(1000, function (Collection $models) use ($bar) {
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
