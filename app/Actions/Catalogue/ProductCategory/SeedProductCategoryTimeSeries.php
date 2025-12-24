<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateTimeSeriesRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\ProductCategory;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedProductCategoryTimeSeries
{
    use AsAction;

    public string $commandSignature = 'product-category:seed-time-series {--frequency=all : The frequency for time series (all, daily, weekly, monthly, quarterly, yearly)}';

    public function asCommand(Command $command): void
    {
        $frequencyOption = $command->option('frequency');
        $productCategories = ProductCategory::all();

        $frequencies = [];
        if ($frequencyOption === 'all') {
            $frequencies = TimeSeriesFrequencyEnum::cases();
        } else {
            $frequencies = [TimeSeriesFrequencyEnum::from($frequencyOption)];
        }

        $totalDispatched = 0;

        foreach ($productCategories as $productCategory) {
            foreach ($frequencies as $frequency) {
                $dispatched = $this->handle($productCategory, $frequency);
                $totalDispatched += $dispatched;
            }
        }
    }

    public function handle(ProductCategory $productCategory, TimeSeriesFrequencyEnum $frequency): void
    {
        EnsureProductCategoryTimeSeries::run($productCategory);

        $timeSeries = $productCategory->timeSeries()
            ->where('frequency', $frequency)
            ->first();

        if (!$timeSeries) {
            return;
        }

        $from = Carbon::now()->subYear()->startOfYear();
        $to = Carbon::now()->endOfDay();

        ProductCategoryHydrateTimeSeriesRecords::dispatch($timeSeries->id, $from, $to);
    }
}
