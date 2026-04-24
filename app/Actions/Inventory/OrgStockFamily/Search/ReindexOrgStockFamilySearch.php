<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Inventory\OrgStockFamily\Search;

use App\Actions\HydrateModel;
use App\Models\Inventory\OrgStockFamily;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexOrgStockFamilySearch extends HydrateModel
{
    public string $commandSignature = 'search:org_stock_families {organisations?*} {--s|slugs=}';


    public function handle(OrgStockFamily $orgStockFamily): void
    {
    }


    protected function getModel(string $slug): OrgStockFamily
    {
        return OrgStockFamily::withTrashed()->where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return OrgStockFamily::withTrashed()->get();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Org Stock Families");
        $count = OrgStockFamily::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        OrgStockFamily::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
