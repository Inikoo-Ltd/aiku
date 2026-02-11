<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Sept 2025 11:11:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection;

use App\Actions\Catalogue\Collection\Hydrators\CollectionHydrateImages;
use App\Actions\Catalogue\Concerns\CanCloneImages;
use App\Models\Catalogue\Collection;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneCollectionImagesFromMaster implements ShouldBeUnique
{
    use AsAction;
    use CanCloneImages;

    public function getJobUniqueId(Collection $collection): string
    {
        return $collection->id;
    }

    public function handle(Collection $collection): void
    {
        if (!$collection->master_collection_id) {
            return;
        }

        $master = $collection->masterCollection;

        if (!$master) {
            return;
        }

        $this->cloneImages($master, $collection);

        $collection->update([
            'image_id' => $master->image_id,
        ]);

        CollectionHydrateImages::run($collection);
        UpdateCollectionWebImages::run($collection);
    }

    public string $commandSignature = 'repair:collection_images_from_master {organisations?*} {--s|slugs=}';

    public function asCommand(Command $command): int
    {
        $organisations = $command->argument('organisations');
        $slugs = $command->option('slugs');

        if ($slugs) {
            $slugArray = explode(',', $slugs);
            $collections = Collection::whereIn('slug', $slugArray)->get();
        } elseif (!empty($organisations)) {
            $collections = Collection::whereHas('organisation', function ($query) use ($organisations) {
                $query->whereIn('slug', $organisations);
            })->get();
        } else {
            $collections = Collection::all();
        }

        $command->info("Repairing Collection Sales Intervals...");
        $command->info("Total collections: " . $collections->count());

        $bar = $command->getOutput()->createProgressBar($collections->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($collections as $collection) {
            $this->handle($collection);
            $bar->advance();
        }

        $bar->finish();
        $command->newLine();
        $command->info("Completed.");

        return 0;
    }

}
