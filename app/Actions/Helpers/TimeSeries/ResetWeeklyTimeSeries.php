<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\TimeSeries;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;

class ResetWeeklyTimeSeries
{
    use WithResetTimeSeriesIntervals;

    public string $commandSignature = 'time-series:reset-weekly';
    public string $commandDescription = 'Reset weekly time series records';

    public function __construct()
    {
        $this->frequency = TimeSeriesFrequencyEnum::WEEKLY;
    }
}
