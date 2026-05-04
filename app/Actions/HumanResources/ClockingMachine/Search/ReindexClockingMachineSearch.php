<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\HumanResources\ClockingMachine\Search;

use App\Actions\HydrateModel;
use App\Models\HumanResources\ClockingMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexClockingMachineSearch extends HydrateModel
{
    public string $commandSignature = 'search:clocking_machines {organisations?*} {--s|slugs=}';


    public function handle(ClockingMachine $clockingMachine): void
    {
    }


    protected function getModel(string $slug): ClockingMachine
    {
        return ClockingMachine::withTrashed()->where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return ClockingMachine::withTrashed()->get();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Clocking Machines");
        $count = ClockingMachine::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        ClockingMachine::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
