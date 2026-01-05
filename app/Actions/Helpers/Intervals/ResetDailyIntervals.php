<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Jan 2025 23:34:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Intervals;

use App\Enums\DateIntervals\DateIntervalEnum;

class ResetDailyIntervals
{
    use WithResetIntervals;

    public string $commandSignature = 'intervals:reset-day';
    public string $commandDescription = 'Reset day intervals';
    public int $jobTries = 1;

    public function __construct()
    {
        $this->intervals = [
            DateIntervalEnum::YESTERDAY,
            DateIntervalEnum::TODAY
        ];
    }


}
