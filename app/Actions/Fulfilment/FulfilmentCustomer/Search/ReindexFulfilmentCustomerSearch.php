<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 01:43:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\FulfilmentCustomer\Search;

use App\Actions\HydrateModel;
use App\Models\Fulfilment\FulfilmentCustomer;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexFulfilmentCustomerSearch extends HydrateModel
{
    public string $commandSignature = 'search:fulfilment_customers {organisations?*} {--s|slugs=}';


    public function handle(FulfilmentCustomer $fulfilmentCustomer): void
    {

    }


    protected function getModel(string $slug): FulfilmentCustomer
    {
        return FulfilmentCustomer::withTrashed()->where('slug', $slug)->first();
    }

    protected function loopAll(Command $command): void
    {

        $command->info("Reindex Fulfilment customers");

        $count = FulfilmentCustomer::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();
        FulfilmentCustomer::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
