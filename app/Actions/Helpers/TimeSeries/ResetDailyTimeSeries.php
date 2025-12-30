<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\TimeSeries;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;

class ResetDailyTimeSeries
{
    use WithResetTimeSeries;

    public string $commandSignature = 'time-series:reset-daily';
    public string $commandDescription = 'Reset daily time series records';

    public function __construct()
    {
        $this->frequency = TimeSeriesFrequencyEnum::DAILY;
    }
}
