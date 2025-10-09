<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 13 Aug 2025 10:34:13 Central European Summer Time, Torremolinos, Spain
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairProductCategoryMissingStats
{
    use WithActionUpdate;


    public function handle(ProductCategory $productCategory, Command $command): void
    {
        if (!$productCategory->stats) {
            $productCategory->stats()->create();
        }
        if (!$productCategory->orderingIntervals) {
            $productCategory->orderingIntervals()->create();
        }

        if (!$productCategory->salesIntervals) {
            $productCategory->salesIntervals()->create();
        }

        if (!$productCategory->orderingStats) {
            $productCategory->orderingStats()->create();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if (!$productCategory->timeSeries()->where('frequency', $frequency)->exists()) {
                $productCategory->timeSeries()->create(['frequency' => $frequency]);
            }
        }
    }


    public string $commandSignature = 'repair:product_category_missing_stats {productCategory?}';

    public function asCommand(Command $command): void
    {
        if ($command->argument('productCategory')) {
            $productCategory = ProductCategory::find($command->argument('productCategory'));
            $this->handle($productCategory, $command);
        } else {
            $count = ProductCategory::withTrashed()->count();

            $bar = $command->getOutput()->createProgressBar($count);
            $bar->setFormat('debug');
            $bar->start();

            ProductCategory::withTrashed()->orderBy('id')
                ->chunk(100, function (Collection $models) use ($bar, $command) {
                    foreach ($models as $model) {
                        $this->handle($model, $command);
                        $bar->advance();
                    }
                });
        }
    }

}
