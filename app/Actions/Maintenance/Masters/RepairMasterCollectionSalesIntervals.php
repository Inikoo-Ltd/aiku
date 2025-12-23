<?php

namespace App\Actions\Maintenance\Masters;

use App\Models\Masters\MasterCollection;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMasterCollectionSalesIntervals
{
    use AsAction;

    public string $commandSignature = 'repair:master-collection-sales-intervals {masterShops?*} {--s|slugs=}';

    public function handle(MasterCollection $masterCollection): void
    {
        $masterCollection->salesIntervals()->updateOrCreate(
            ['master_collection_id' => $masterCollection->id],
            ['master_collection_id' => $masterCollection->id]
        );
    }

    public function asCommand(Command $command): int
    {
        $masterShops = $command->argument('masterShops');
        $slugs = $command->option('slugs');

        if ($slugs) {
            $slugArray = explode(',', $slugs);
            $masterCollections = MasterCollection::whereIn('slug', $slugArray)->get();
        } elseif (!empty($masterShops)) {
            $masterCollections = MasterCollection::whereHas('masterShop', function ($query) use ($masterShops) {
                $query->whereIn('slug', $masterShops);
            })->get();
        } else {
            $masterCollections = MasterCollection::all();
        }

        $command->info("Repairing Master Collection Sales Intervals...");
        $command->info("Total master collections: " . $masterCollections->count());

        $bar = $command->getOutput()->createProgressBar($masterCollections->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($masterCollections as $masterCollection) {
            $this->handle($masterCollection);
            $bar->advance();
        }

        $bar->finish();
        $command->newLine();
        $command->info("Completed.");

        return 0;
    }
}
