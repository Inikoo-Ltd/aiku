<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Traits;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Carbon\Carbon;

trait WithTimeSeriesRecordsGeneration
{
    protected function generatePeriods(Carbon $from, Carbon $to, TimeSeriesFrequencyEnum $frequency): array
    {
        $periods = [];
        $current = $from->copy()->setTimezone('UTC');
        $toUtc = $to->copy()->setTimezone('UTC');

        while ($current->lt($toUtc)) {
            $periodStart = $current->copy();
            $periodEnd = match ($frequency) {
                TimeSeriesFrequencyEnum::DAILY => $current->copy()->endOfDay(),
                TimeSeriesFrequencyEnum::WEEKLY => $current->copy()->endOfWeek(),
                TimeSeriesFrequencyEnum::MONTHLY => $current->copy()->endOfMonth(),
                TimeSeriesFrequencyEnum::QUARTERLY => $current->copy()->endOfQuarter(),
                TimeSeriesFrequencyEnum::YEARLY => $current->copy()->endOfYear(),
            };

            if ($periodEnd->gt($toUtc)) {
                $periodEnd = $toUtc->copy();
            }

            $periods[] = [
                'from' => $periodStart,
                'to' => $periodEnd,
            ];

            $current = match ($frequency) {
                TimeSeriesFrequencyEnum::DAILY => $periodEnd->copy()->addDay()->startOfDay(),
                TimeSeriesFrequencyEnum::WEEKLY => $periodEnd->copy()->addWeek()->startOfWeek(),
                TimeSeriesFrequencyEnum::MONTHLY => $periodEnd->copy()->addMonth()->startOfMonth(),
                TimeSeriesFrequencyEnum::QUARTERLY => $periodEnd->copy()->addQuarter()->startOfQuarter(),
                TimeSeriesFrequencyEnum::YEARLY => $periodEnd->copy()->addYear()->startOfYear(),
            };
        }

        return $periods;
    }
}
