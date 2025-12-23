<?php

namespace App\Actions\Maintenance\Catalogue;

use App\Models\Catalogue\Collection;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairCollectionSalesIntervals
{
    use AsAction;

    public string $commandSignature = 'repair:collection-sales-intervals {organisations?*} {--s|slugs=}';

    public function handle(Collection $collection): void
    {
        $collection->salesIntervals()->updateOrCreate(
            ['collection_id' => $collection->id],
            ['collection_id' => $collection->id]
        );
    }

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
