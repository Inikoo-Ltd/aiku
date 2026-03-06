<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Traits;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait BuildsAggregatedTimeSeriesQuery
{
    protected function applyAggregatedFrequencyGrouping(Builder $query, TimeSeriesFrequencyEnum $frequency, array $selects): Builder
    {
        return match ($frequency) {
            TimeSeriesFrequencyEnum::YEARLY => $query
                ->select(array_merge([DB::raw('EXTRACT(YEAR FROM "from") as year')], $selects))
                ->groupBy(DB::raw('EXTRACT(YEAR FROM "from")')),

            TimeSeriesFrequencyEnum::QUARTERLY => $query
                ->select(array_merge([
                    DB::raw('EXTRACT(YEAR FROM "from") as year'),
                    DB::raw('EXTRACT(QUARTER FROM "from") as quarter'),
                ], $selects))
                ->groupBy(DB::raw('EXTRACT(YEAR FROM "from")'), DB::raw('EXTRACT(QUARTER FROM "from")')),

            TimeSeriesFrequencyEnum::MONTHLY => $query
                ->select(array_merge([
                    DB::raw('EXTRACT(YEAR FROM "from") as year'),
                    DB::raw('EXTRACT(MONTH FROM "from") as month'),
                ], $selects))
                ->groupBy(DB::raw('EXTRACT(YEAR FROM "from")'), DB::raw('EXTRACT(MONTH FROM "from")')),

            TimeSeriesFrequencyEnum::WEEKLY => $query
                ->select(array_merge([
                    DB::raw('EXTRACT(YEAR FROM "from") as year'),
                    DB::raw('EXTRACT(WEEK FROM "from") as week'),
                ], $selects))
                ->groupBy(DB::raw('EXTRACT(YEAR FROM "from")'), DB::raw('EXTRACT(WEEK FROM "from")')),

            default => $query,
        };
    }
}
