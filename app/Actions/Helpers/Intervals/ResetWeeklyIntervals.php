<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 04 Jan 2025 00:45:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Intervals;

use App\Enums\DateIntervals\DateIntervalEnum;

class ResetWeeklyIntervals
{
    use WithResetIntervals;

    public string $commandSignature = 'intervals:reset-week';
    public string $commandDescription = 'Reset weekly intervals';
    public int $jobTries = 1;
    public function __construct()
    {
        $this->intervals = [
            DateIntervalEnum::LAST_WEEK,
            DateIntervalEnum::WEEK_TO_DAY
        ];
    }

}
