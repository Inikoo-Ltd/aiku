<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Aug 2024 22:06:41 Central Indonesia Time, Bali Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Prospect\Search;

use App\Actions\HydrateModel;
use App\Models\CRM\Prospect;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexProspectSearch extends HydrateModel
{
    public string $commandSignature = 'search:prospects {organisations?*} {--s|slugs=}';


    public function handle(Prospect $prospect): void
    {

    }


    protected function getModel(string $slug): Prospect
    {
        return Prospect::withTrashed()->where('slug', $slug)->first();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Prospects");
        $count = Prospect::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Prospect::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
