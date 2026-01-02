<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Maintenance\Masters;

use App\Actions\Helpers\TimeSeries\EnsureTimeSeries;
use App\Models\Masters\MasterAsset;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMasterAssetTimeSeries
{
    use AsAction;

    public string $commandSignature = 'repair:master-asset-time-series {masterShops?*} {--s|slugs=}';

    public function handle(MasterAsset $masterAsset): void
    {
        EnsureTimeSeries::run($masterAsset);
    }

    public function asCommand(Command $command): int
    {
        $masterShops = $command->argument('masterShops');
        $slugs = $command->option('slugs');

        if ($slugs) {
            $slugArray = explode(',', $slugs);
            $masterAssets = MasterAsset::whereIn('slug', $slugArray)->get();
        } elseif (!empty($masterShops)) {
            $masterAssets = MasterAsset::whereHas('masterShop', function ($query) use ($masterShops) {
                $query->whereIn('slug', $masterShops);
            })->get();
        } else {
            $masterAssets = MasterAsset::all();
        }

        $command->info("Repairing Master Asset Time Series...");
        $command->info("Total master assets: " . $masterAssets->count());

        $bar = $command->getOutput()->createProgressBar($masterAssets->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($masterAssets as $masterAsset) {
            $this->handle($masterAsset);
            $bar->advance();
        }

        $bar->finish();
        $command->newLine();
        $command->info("Completed.");

        return 0;
    }
}
