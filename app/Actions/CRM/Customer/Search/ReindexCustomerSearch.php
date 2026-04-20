<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Aug 2024 22:13:51 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\Search;

use App\Actions\HydrateModel;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexCustomerSearch extends HydrateModel
{
    public string $commandSignature = 'search:customers {organisations?*} {--s|slugs=}';


    public function handle(Customer $customer): void
    {

    }

    protected function getModel(string $slug): Customer
    {
        return Customer::withTrashed()->where('slug', $slug)->first();
    }


    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Customers");
        $count = Customer::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Customer::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }

}
