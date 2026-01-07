<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Jan 2025 17:46:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Intervals;

use App\Enums\DateIntervals\DateIntervalEnum;

class ResetYearIntervals
{
    use WithResetIntervals;

    public string $commandSignature = 'intervals:reset-year';
    public string $commandDescription = 'Reset year intervals';
    public int $jobTries = 1;

    public function __construct()
    {
        $this->intervals         = [
            DateIntervalEnum::YEAR_TO_DAY
        ];
        $this->doPreviousPeriods = ['previous_years'];
    }


}
