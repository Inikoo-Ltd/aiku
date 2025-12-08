<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 19-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Procurement\PurchaseOrder\Search;

use App\Actions\HydrateModel;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexPurchaseOrderSearch extends HydrateModel
{
    public string $commandSignature = 'search:purchase_orders {organisations?*} {--s|slugs=} ';

    public function handle(PurchaseOrder $purchaseOrder): void
    {
        PurchaseOrderRecordSearch::run($purchaseOrder);
    }

    protected function getModel(string $slug): PurchaseOrder
    {
        return PurchaseOrder::where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return PurchaseOrder::all();
    }

    protected function loopAll(Command $command): void
    {
        $command->info('Reindex Org Suppliers');
        $count = PurchaseOrder::count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        PurchaseOrder::chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info('');
    }
}
