<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 01:39:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet\Search;

use App\Actions\HydrateModel;
use App\Models\Fulfilment\Pallet;
use Illuminate\Support\Collection;
use Illuminate\Console\Command;

class ReindexPalletSearch extends HydrateModel
{
    public string $commandSignature = 'search:pallets {organisations?*} {--s|slugs=}';


    public function handle(Pallet $pallet): void
    {
        PalletRecordSearch::run($pallet);
    }


    protected function getModel(string $slug): Pallet
    {
        return Pallet::withTrashed()->where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return Pallet::withTrashed()->get();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Pallets");
        $count = Pallet::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Pallet::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
