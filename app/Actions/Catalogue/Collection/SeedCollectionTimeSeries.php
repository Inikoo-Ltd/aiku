<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Collection;

use App\Actions\Catalogue\Collection\Hydrators\CollectionHydrateTimeSeriesRecords;
use App\Actions\Helpers\TimeSeries\SeedsTimeSeries;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Models\Catalogue\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedCollectionTimeSeries
{
    use AsAction;
    use SeedsTimeSeries;

    public string $commandSignature = 'seed:collection-time-series {--frequency=all : The frequency for time series (all, daily, weekly, monthly, quarterly, yearly)}';

    protected function getModelsToSeed(): EloquentCollection
    {
        return Collection::whereNotIn('state', [
            CollectionStateEnum::IN_PROCESS
        ])->get();
    }

    protected function getModelFriendlyName(): string
    {
        return 'collections';
    }

    protected function getHydratorClass(): string
    {
        return CollectionHydrateTimeSeriesRecords::class;
    }
}
