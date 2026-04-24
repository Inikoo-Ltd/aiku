<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 15-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Discounts\Offer\Search;

use App\Actions\HydrateModel;
use App\Models\Discounts\Offer;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexOfferSearch extends HydrateModel
{
    public string $commandSignature = 'search:offers {organisations?*} {--s|slugs=}';


    public function handle(Offer $offer): void
    {

    }

    protected function getModel(string $slug): Offer
    {
        return Offer::withTrashed()->where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return Offer::withTrashed()->get();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Offers");
        $count = Offer::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Offer::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
