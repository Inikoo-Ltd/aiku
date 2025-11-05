<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Oct 2025 16:09:29 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Masters\MasterCollection;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairMasterCollectionsMissingStats
{
    use WithActionUpdate;


    public function handle(MasterCollection $masterCollection, Command $command): void
    {
        if (!$masterCollection->stats) {
            $masterCollection->stats()->create();
        }

        if (!$masterCollection->salesIntervals) {
            $masterCollection->salesIntervals()->create();
        }

        if (!$masterCollection->orderingStats) {
            $masterCollection->orderingStats()->create();
        }
    }


    public string $commandSignature = 'repair:master_collection_missing_stats {masterCollection?}';

    public function asCommand(Command $command): void
    {
        if ($command->argument('masterCollection')) {
            $masterCollection = MasterCollection::find($command->argument('masterCollection'));
            $this->handle($masterCollection, $command);
        } else {
            $count = MasterCollection::withTrashed()->count();

            $bar = $command->getOutput()->createProgressBar($count);
            $bar->setFormat('debug');
            $bar->start();

            MasterCollection::withTrashed()->orderBy('id')
                ->chunk(100, function (Collection $models) use ($bar, $command) {
                    foreach ($models as $model) {
                        $this->handle($model, $command);
                        $bar->advance();
                    }
                });
        }
    }

}
