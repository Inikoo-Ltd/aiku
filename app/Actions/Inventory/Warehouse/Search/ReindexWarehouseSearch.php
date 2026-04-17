<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Aug 2024 21:55:06 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Warehouse\Search;

use App\Actions\HydrateModel;
use App\Models\Inventory\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexWarehouseSearch extends HydrateModel
{
    public string $commandSignature = 'search:warehouses {organisations?*} {--s|slugs=}';


    public function handle(Warehouse $warehouse): void
    {
    }


    protected function getModel(string $slug): Warehouse
    {
        return Warehouse::withTrashed()->where('slug', $slug)->first();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Warehouses");
        $count = Warehouse::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Warehouse::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
