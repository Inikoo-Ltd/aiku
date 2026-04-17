<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 20-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Accounting\PaymentAccount\Search;

use App\Actions\HydrateModel;
use App\Models\Accounting\PaymentAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexPaymentAccountSearch extends HydrateModel
{
    public string $commandSignature = 'search:payment_accounts {organisations?*} {--s|slugs=} ';


    public function handle(PaymentAccount $paymentAccount): void
    {

    }

    protected function getModel(string $slug): PaymentAccount
    {
        return PaymentAccount::withTrashed()->where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return PaymentAccount::withTrashed()->get();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Payment Accounts");
        $count = PaymentAccount::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        PaymentAccount::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
