<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 20-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Billables\Charge\Search;

use App\Actions\HydrateModel;
use App\Models\Billables\Charge;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexChargeSearch extends HydrateModel
{
    public string $commandSignature = 'search:charges {organisations?*} {--s|slugs=} ';


    public function handle(Charge $charge): void
    {

    }

    protected function getModel(string $slug): Charge
    {
        return Charge::withTrashed()->where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return Charge::withTrashed()->get();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Charges");
        $count = Charge::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Charge::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
