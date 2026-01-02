<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateTimeSeriesRecords;
use App\Actions\Helpers\TimeSeries\EnsureTimeSeries;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedProductTimeSeries
{
    use AsAction;

    public string $commandSignature = 'seed:product-time-series {--frequency=all : The frequency for time series (all, daily, weekly, monthly, quarterly, yearly)}';

    public function asCommand(Command $command): void
    {
        $frequencyOption = $command->option('frequency');
        $products = Product::whereNotNull('asset_id')->whereNotIn('state', [ProductStateEnum::IN_PROCESS, ProductStateEnum::DISCONTINUED])->get();

        $frequencies = [];
        if ($frequencyOption === 'all') {
            $frequencies = TimeSeriesFrequencyEnum::cases();
        } else {
            $frequencies = [TimeSeriesFrequencyEnum::from($frequencyOption)];
        }

        $totalDispatched = 0;

        foreach ($products as $product) {
            foreach ($frequencies as $frequency) {
                $dispatched = $this->handle($product, $frequency);
                $totalDispatched += $dispatched;
            }
        }

        $command->info("Dispatched {$totalDispatched} time series seed jobs for products.");
    }

    public function handle(Product $product, TimeSeriesFrequencyEnum $frequency): void
    {
        if (!$product->asset) {
            return;
        }

        EnsureTimeSeries::run($product->asset);

        $timeSeries = $product->asset->timeSeries()
            ->where('frequency', $frequency)
            ->first();

        if (!$timeSeries) {
            return;
        }

        $from = Carbon::now('UTC')->subYear()->startOfYear();
        $to = Carbon::now('UTC')->endOfDay();

        ProductHydrateTimeSeriesRecords::dispatch($timeSeries->id, $from, $to)
            ->WithoutOverlapping()
            ->delay(now()->addMinute())
            ->onQueue('low-priority');
    }
}
