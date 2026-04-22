<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\HumanResources\JobPosition\Search;

use App\Actions\HydrateModel;
use App\Models\HumanResources\JobPosition;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexJobPositionSearch extends HydrateModel
{
    public string $commandSignature = 'search:job_positions {organisations?*} {--s|slugs=}';


    public function handle(JobPosition $jobPosition): void
    {
    }


    protected function getModel(string $slug): JobPosition
    {
        return JobPosition::where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return JobPosition::all();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Job Positions");
        $count = JobPosition::count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        JobPosition::chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
