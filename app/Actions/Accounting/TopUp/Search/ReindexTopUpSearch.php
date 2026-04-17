<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 01:43:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\TopUp\Search;

use App\Actions\HydrateModel;
use App\Models\Accounting\TopUp;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexTopUpSearch extends HydrateModel
{
    public string $commandSignature = 'search:top_ups {organisations?*} {--s|slugs=}';


    public function handle(TopUp $topUp): void
    {

    }


    protected function getModel(string $slug): TopUp
    {
        return TopUp::where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return TopUp::all();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Top Ups");
        $count = TopUp::count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        TopUp::chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
