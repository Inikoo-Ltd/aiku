<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\TimeSeries;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;

class ResetYearlyTimeSeries
{
    use WithResetTimeSeries;

    public string $commandSignature = 'time-series:reset-yearly';
    public string $commandDescription = 'Reset yearly time series records';

    public function __construct()
    {
        $this->frequency = TimeSeriesFrequencyEnum::YEARLY;
    }
}
