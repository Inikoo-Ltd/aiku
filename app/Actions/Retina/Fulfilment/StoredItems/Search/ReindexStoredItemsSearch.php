<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 07-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Fulfilment\StoredItems\Search;

use App\Actions\HydrateModel;
use App\Models\Accounting\Invoice;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexStoredItemsSearch extends HydrateModel
{
    public string $commandSignature = 'search:retina_stored_items {organisations?*} {--s|slugs=}';

    public function handle(StoredItem $storedItem): void
    {
        StoredItemRecordSearch::run($storedItem);
    }

    protected function getModel(string $slug): Invoice
    {
        return StoredItem::where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return StoredItem::get();
    }

    protected function loopAll(Command $command): void
    {
        $command->info('Reindex Stored Items');
        $count = StoredItem::count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        StoredItem::chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info('');
    }
}
