<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:22:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Service\Search;

use App\Actions\HydrateModel;
use App\Models\Billables\Service;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexServiceSearch extends HydrateModel
{
    public string $commandSignature = 'search:services {organisations?*} {--s|slugs=}';


    public function handle(Service $service): void
    {

    }

    protected function getModel(string $slug): Service
    {
        return Service::withTrashed()->where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return Service::withTrashed()->get();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Services");
        $count = Service::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Service::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
