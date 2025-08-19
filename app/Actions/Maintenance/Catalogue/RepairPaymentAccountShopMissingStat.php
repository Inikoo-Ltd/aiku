<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 13 Aug 2025 10:34:13 Central European Summer Time, Torremolinos, Spain
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairPaymentAccountShopMissingStat
{
    use WithActionUpdate;


    public function handle(PaymentAccountShop $paymentAccountShop): void
    {
        if (!$paymentAccountShop->stats) {
            $paymentAccountShop->stats()->create();
        }
    }


    public string $commandSignature = 'repair:payment-account-shop-missing-stat {shop?}';

    public function asCommand(Command $command): void
    {
        if ($command->argument('shop')) {
            $shop = Shop::where('slug', $command->argument('shop'))->first();
        
            $count = PaymentAccountShop::where('shop_id', $shop->id)->count();
            $bar = $command->getOutput()->createProgressBar($count);
            $bar->setFormat('debug');
            $bar->start();

            PaymentAccountShop::where('shop_id', $shop->id)->orderBy('id')
                ->chunk(100, function (Collection $models) use ($bar) {
                    foreach ($models as $model) {
                        $this->handle($model);
                        $bar->advance();
                    }
                });
        } else {
            $count = PaymentAccountShop::count();

            $bar = $command->getOutput()->createProgressBar($count);
            $bar->setFormat('debug');
            $bar->start();

            PaymentAccountShop::orderBy('id')
                ->chunk(100, function (Collection $models) use ($bar) {
                    foreach ($models as $model) {
                        $this->handle($model);
                        $bar->advance();
                    }
                });
        }
    }

}
