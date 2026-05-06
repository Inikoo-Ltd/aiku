<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 01:38:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery\Search;

use App\Actions\HydrateModel;
use App\Models\Fulfilment\PalletDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexPalletDeliverySearch extends HydrateModel
{
    public string $commandSignature = 'search:pallet_deliveries {organisations?*} {--s|slugs=}';


    public function handle(PalletDelivery $palletDelivery): void
    {
    }

    protected function getModel(string $slug): PalletDelivery
    {
        return PalletDelivery::withTrashed()->where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return PalletDelivery::withTrashed()->get();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Pallet Deliveries");
        $count = PalletDelivery::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        PalletDelivery::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
