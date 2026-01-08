<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\Intervals;

use App\Actions\Dropshipping\Platform\Hydrators\PlatformHydrateSalesIntervals;
use App\Models\Dropshipping\Platform;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessResetIntervalsPlatforms
{
    use AsAction;

    public function handle(array $intervals, array $doPreviousPeriods): void
    {
        foreach (
            Platform::all() as $platform
        ) {
            PlatformHydrateSalesIntervals::dispatch(
                platform: $platform,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );
        }
    }
}
