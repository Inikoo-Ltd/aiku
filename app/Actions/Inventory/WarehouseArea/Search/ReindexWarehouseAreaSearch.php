<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 01:43:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\WarehouseArea\Search;

use App\Actions\HydrateModel;
use App\Models\Inventory\WarehouseArea;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexWarehouseAreaSearch extends HydrateModel
{
    public string $commandSignature = 'search:warehouse_areas {organisations?*} {--s|slugs=}';


    public function handle(WarehouseArea $warehouseArea): void
    {
    }


    protected function getModel(string $slug): WarehouseArea
    {
        return WarehouseArea::withTrashed()->where('slug', $slug)->first();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Warehouse areas");
        $count = WarehouseArea::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        WarehouseArea::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
