<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 Sept 2025 12:58:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Helper\ProgressBar;

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
            ProgressBar::setFormatDefinition(
                'aiku_eta',
                ' %current%/%max% [%bar%] %percent:3s%% | Elapsed: %elapsed:6s% | ETA: %remaining:6s%'
            );
            $bar = $command->getOutput()->createProgressBar($count);
            $bar->setFormat('aiku_eta');
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

            ProgressBar::setFormatDefinition(
                'aiku_eta',
                ' %current%/%max% [%bar%] %percent:3s%% | Elapsed: %elapsed:6s% | ETA: %remaining:6s%'
            );
            $bar = $command->getOutput()->createProgressBar($count);
            $bar->setFormat('aiku_eta');
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
