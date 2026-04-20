<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 20-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Accounting\Payment\Search;

use App\Actions\HydrateModel;
use App\Models\Accounting\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexPaymentSearch extends HydrateModel
{
    public string $commandSignature = 'search:payments {organisations?*} {--s|slugs=} ';


    public function handle(Payment $payment): void
    {

    }

    protected function getModel(string $slug): Payment
    {
        return Payment::withTrashed()->where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return Payment::withTrashed()->get();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Payments");
        $count = Payment::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Payment::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
