<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 07-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Fulfilment\PalletReturn\Search;

use App\Actions\HydrateModel;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexPalletReturnSearch extends HydrateModel
{
    public string $commandSignature = 'search:retina_pallet_returns {organisations?*} {--s|slugs=}';


    public function handle(PalletReturn $palletReturn): void
    {
        PalletReturnRecordSearch::run($palletReturn);
    }

    protected function getModel(string $slug): PalletReturn
    {
        return PalletReturn::withTrashed()->where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return PalletReturn::withTrashed()->get();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Pallet Returns");
        $count = PalletReturn::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        PalletReturn::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
