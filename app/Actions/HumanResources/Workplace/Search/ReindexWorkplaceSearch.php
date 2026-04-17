<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\HumanResources\Workplace\Search;

use App\Actions\HydrateModel;
use App\Models\HumanResources\Workplace;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexWorkplaceSearch extends HydrateModel
{
    public string $commandSignature = 'search:workplaces {organisations?*} {--s|slugs=}';


    public function handle(Workplace $workplace): void
    {
    }


    protected function getModel(string $slug): Workplace
    {
        return Workplace::withTrashed()->where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return Workplace::withTrashed()->get();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Workplaces");
        $count = Workplace::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Workplace::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
