<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 01:43:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Location\Search;

use App\Actions\HydrateModel;
use App\Models\Inventory\Location;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexLocationSearch extends HydrateModel
{
    public string $commandSignature = 'search:locations {organisations?*} {--s|slugs=}';


    public function handle(Location $location): void
    {

    }


    protected function getModel(string $slug): Location
    {
        return Location::withTrashed()->where('slug', $slug)->first();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Locations");
        $count = Location::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Location::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
