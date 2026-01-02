<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Collection;

use App\Actions\Catalogue\Collection\Hydrators\CollectionHydrateTimeSeriesRecords;
use App\Actions\Helpers\TimeSeries\EnsureTimeSeries;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Collection;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedCollectionTimeSeries
{
    use AsAction;

    public string $commandSignature = 'seed:collection-time-series {--frequency=all : The frequency for time series (all, daily, weekly, monthly, quarterly, yearly)}';

    public function asCommand(Command $command): void
    {
        $frequencyOption = $command->option('frequency');
        $collections     = Collection::whereNotIn('state', [
            CollectionStateEnum::IN_PROCESS
        ])->get();

        if ($frequencyOption === 'all') {
            $frequencies = TimeSeriesFrequencyEnum::cases();
        } else {
            $frequencies = [TimeSeriesFrequencyEnum::from($frequencyOption)];
        }

        $totalDispatched = 0;

        foreach ($collections as $collection) {
            foreach ($frequencies as $frequency) {
                $this->handle($collection, $frequency);
                $totalDispatched++;
            }
        }

        $command->info("Dispatched $totalDispatched time series seed jobs for collections.");
    }

    public function handle(Collection $collection, TimeSeriesFrequencyEnum $frequency): void
    {
        EnsureTimeSeries::run($collection);

        $timeSeries = $collection->timeSeries()
            ->where('frequency', $frequency)
            ->first();

        if (!$timeSeries) {
            return;
        }

        $from = Carbon::now('UTC')->subYear()->startOfYear();
        $to   = Carbon::now('UTC')->endOfDay();

        CollectionHydrateTimeSeriesRecords::dispatch($timeSeries->id, $from, $to)
            ->onQueue('low-priority');
    }
}
