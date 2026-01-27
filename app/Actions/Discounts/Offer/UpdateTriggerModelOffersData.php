<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Jan 2026 14:08:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateTriggerModelOffersData
{
    use AsAction;

    public function handle(Offer $offer): void
    {

        if ($offer->trigger_type == 'ProductCategory') {
            UpdateProductCategoryOffersData::run($offer);
        }


    }

    public function getCommandSignature(): string
    {
        return 'discounts:offer:update-trigger-model-offers-data {offer?}';
    }

    public function asCommand(Command $command): int
    {

        $aikuShops = Shop::where('is_aiku', true)->pluck('id')->toArray();


        if ($slug = $command->argument('offer')) {
            $offer = Offer::where('slug', $slug)->firstOrFail();
            $this->handle($offer);
        } else {
            $offers = Offer::whereIn('shop_id', $aikuShops);
            $count  = $offers->count();

            $progressBar = $command->getOutput()->createProgressBar($count);
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %elapsed:6s%/%estimated:-6s% -- %memory:6s%');
            $progressBar->start();

            $offers->chunk(100, function ($offers) use ($progressBar) {
                foreach ($offers as $offer) {
                    $this->handle($offer);
                    $progressBar->advance();
                }
            });

            $progressBar->finish();
            $command->newLine();
        }

        return 0;
    }

}
