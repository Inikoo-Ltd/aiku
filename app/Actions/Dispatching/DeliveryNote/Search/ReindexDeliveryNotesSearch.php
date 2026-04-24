<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Sept 2024 23:41:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\Search;

use App\Actions\HydrateModel;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexDeliveryNotesSearch extends HydrateModel
{
    public string $commandSignature = 'search:delivery_notes {organisations?*} {--s|slugs=}';


    public function handle(DeliveryNote $deliveryNote): void
    {

    }

    protected function getModel(string $slug): DeliveryNote
    {
        return DeliveryNote::withTrashed()->where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return DeliveryNote::withTrashed()->get();
    }

    protected function loopAll(Command $command): void
    {
        $command->info("Reindex Delivery Notes");
        $count = DeliveryNote::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        DeliveryNote::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");
    }
}
