<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Helpers\TimeSeries\EnsureTimeSeries;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateTimeSeriesRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Masters\MasterAsset;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedMasterAssetTimeSeries
{
    use AsAction;

    public string $commandSignature = 'seed:master-asset-time-series {--frequency=all : The frequency for time series (all, daily, weekly, monthly, quarterly, yearly)}';

    public function asCommand(Command $command): void
    {
        $frequencyOption = $command->option('frequency');
        $masterAssets = MasterAsset::where('status', true)->get();


        if ($frequencyOption === 'all') {
            $frequencies = TimeSeriesFrequencyEnum::cases();
        } else {
            $frequencies = [TimeSeriesFrequencyEnum::from($frequencyOption)];
        }

        $totalDispatched = 0;

        foreach ($masterAssets as $masterAsset) {
            foreach ($frequencies as $frequency) {
                $dispatched = $this->handle($masterAsset, $frequency);
                $totalDispatched += $dispatched;
            }
        }

        $command->info("Dispatched $totalDispatched time series seed jobs for master assets.");
    }

    public function handle(MasterAsset $masterAsset, TimeSeriesFrequencyEnum $frequency): bool
    {
        EnsureTimeSeries::run($masterAsset);

        $timeSeries = $masterAsset->timeSeries()
            ->where('frequency', $frequency)
            ->first();

        if (!$timeSeries) {
            return false;
        }

        $from = Carbon::now('UTC')->subYear()->startOfYear();
        $to = Carbon::now('UTC')->endOfDay();

        MasterAssetHydrateTimeSeriesRecords::dispatch($timeSeries->id, $from, $to)->onQueue('low-priority');
        return true;
    }
}
