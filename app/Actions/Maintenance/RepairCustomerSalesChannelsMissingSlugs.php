<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 May 2025 11:05:58 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairCustomerSalesChannelsMissingSlugs
{
    use WithActionUpdate;


    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {

        $customerSalesChannel->generateSlug();
        $customerSalesChannel->save();



    }


    public string $commandSignature = 'repair_customer_sales_channels:slugs';

    public function asCommand(Command $command): void
    {
        $count = CustomerSalesChannel::count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        CustomerSalesChannel::orderBy('id')
        ->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });
    }

}
