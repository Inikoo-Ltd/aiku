<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\TimeSeries;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;

class ResetMonthlyTimeSeries
{
    use WithResetTimeSeriesIntervals;

    public string $commandSignature = 'time-series:reset-monthly';
    public string $commandDescription = 'Reset monthly time series records';

    public function __construct()
    {
        $this->frequency = TimeSeriesFrequencyEnum::MONTHLY;
    }
}
