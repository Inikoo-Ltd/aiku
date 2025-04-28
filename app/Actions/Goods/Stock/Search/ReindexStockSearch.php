<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Goods\Stock\Search;

use App\Actions\HydrateModel;
use App\Models\Goods\Stock;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexStockSearch extends HydrateModel
{
    public string $commandSignature = 'search:stocks {organisations?*} {--s|slugs=}';


    public function handle(Stock $stock): void
    {
        StockRecordSearch::run($stock);
    }


    protected function getModel(string $slug): Stock
    {
        return Stock::withTrashed()->where('slug', $slug)->first();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Stocks");
        $count = Stock::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Stock::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
