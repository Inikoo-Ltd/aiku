<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 16:01:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop;

use App\Actions\HydrateModel;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateInvoiceIntervals;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateOrderInBasketAtCreatedIntervals;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateOrderInBasketAtCustomerUpdateIntervals;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateOrders;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateRegistrationIntervals;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateSalesIntervals;
use App\Actions\Traits\WithNormalise;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class HydrateMasterShopSales extends HydrateModel
{
    use WithNormalise;

    public string $commandSignature = 'hydrate:master_shops_sales';


    public function handle(MasterShop $masterShop): void
    {
        MasterShopHydrateSalesIntervals::run($masterShop->id);
        MasterShopHydrateOrders::run($masterShop->id);
        MasterShopHydrateInvoiceIntervals::run($masterShop->id);
        MasterShopHydrateRegistrationIntervals::run($masterShop->id);
        MasterShopHydrateOrderInBasketAtCreatedIntervals::run($masterShop->id);
        MasterShopHydrateOrderInBasketAtCustomerUpdateIntervals::run($masterShop->id);
    }


    public function asCommand(Command $command): int
    {
        $command->info("Hydrating Master Shops Sales");
        $count = MasterShop::count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        MasterShop::chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");


        return 0;
    }
}
