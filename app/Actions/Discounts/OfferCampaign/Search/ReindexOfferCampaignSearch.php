<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 15-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Discounts\OfferCampaign\Search;

use App\Actions\HydrateModel;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexOfferCampaignSearch extends HydrateModel
{
    public string $commandSignature = 'search:offer_campaigns {organisations?*} {--s|slugs=}';


    public function handle(OfferCampaign $offerCampaign): void
    {
    }

    protected function getModel(string $slug): OfferCampaign
    {
        return OfferCampaign::withTrashed()->where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return OfferCampaign::withTrashed()->get();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Offer Campaigns");
        $count = OfferCampaign::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        OfferCampaign::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }


}
