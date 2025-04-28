<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Goods\StockFamily\Search;

use App\Actions\HydrateModel;
use App\Models\Goods\StockFamily;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexStockFamilySearch extends HydrateModel
{
    public string $commandSignature = 'search:stock_families {organisations?*} {--s|slugs=}';


    public function handle(StockFamily $stockFamily): void
    {
        StockFamilyRecordSearch::run($stockFamily);
    }


    protected function getModel(string $slug): StockFamily
    {
        return StockFamily::withTrashed()->where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return StockFamily::withTrashed()->get();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Stock Families");
        $count = StockFamily::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        StockFamily::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
