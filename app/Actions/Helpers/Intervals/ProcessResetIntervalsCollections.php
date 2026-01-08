<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\Intervals;

use App\Actions\Catalogue\Collection\Hydrators\CollectionHydrateOrderingIntervals;
use App\Actions\Catalogue\Collection\Hydrators\CollectionHydrateSalesIntervals;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Models\Catalogue\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessResetIntervalsCollections
{
    use AsAction;

    public string $jobQueue = 'default-long';

    public function handle(array $intervals, array $doPreviousPeriods): void
    {
        foreach (
            Collection::where('state', CollectionStateEnum::ACTIVE)->get() as $collection
        ) {
            CollectionHydrateSalesIntervals::dispatch(
                collectionId: $collection->id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            CollectionHydrateOrderingIntervals::dispatch(
                collectionId: $collection->id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );
        }

        foreach (
            Collection::where('state', '!=', CollectionStateEnum::ACTIVE)->get() as $collection
        ) {
            CollectionHydrateSalesIntervals::dispatch(
                collectionId: $collection->id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            )->delay(now()->addMinutes(60))->onQueue('low-priority');

            CollectionHydrateOrderingIntervals::dispatch(
                collectionId: $collection->id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            )->delay(now()->addMinutes(60))->onQueue('low-priority');
        }
    }
}
