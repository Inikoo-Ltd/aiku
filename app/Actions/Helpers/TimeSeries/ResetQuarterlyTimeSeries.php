<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\TimeSeries;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;

class ResetQuarterlyTimeSeries
{
    use WithResetTimeSeries;

    public string $commandSignature = 'time-series:reset-quarterly';
    public string $commandDescription = 'Reset quarterly time series records';

    public function __construct()
    {
        $this->frequency = TimeSeriesFrequencyEnum::QUARTERLY;
    }
}
