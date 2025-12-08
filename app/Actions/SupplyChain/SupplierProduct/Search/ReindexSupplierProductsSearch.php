<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 14:47:12 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct\Search;

use App\Actions\HydrateModel;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexSupplierProductsSearch extends HydrateModel
{
    use AsAction;

    public string $commandSignature = 'search:supplier_products {organisations?*} {--s|slugs=}';

    public function handle(SupplierProduct $supplierProduct): void
    {
        SupplierProductRecordSearch::run($supplierProduct);
    }

    protected function getModel(string $slug): SupplierProduct
    {
        return SupplierProduct::withTrashed()->where('slug', $slug)->first();
    }

    protected function loopAll(Command $command): void
    {
        $command->info('Reindex Supplier Products');
        $count = SupplierProduct::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        SupplierProduct::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info('');
    }
}
