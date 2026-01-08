<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 04 Jan 2025 00:00:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Intervals;

use App\Enums\DateIntervals\DateIntervalEnum;

class ResetMonthlyIntervals
{
    use WithResetIntervals;

    public string $commandSignature = 'intervals:reset-month';
    public string $commandDescription = 'Reset monthly intervals';
    public int $jobTries = 1;

    public function __construct()
    {
        $this->intervals = [
            DateIntervalEnum::LAST_MONTH,
            DateIntervalEnum::MONTH_TO_DAY
        ];
    }


}
