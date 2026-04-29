<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 29 Apr 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Traits\Dashboards;

use App\Actions\Helpers\Dashboard\DashboardIntervalFilters;
use App\Enums\DateIntervals\DateIntervalEnum;
use Illuminate\Support\Arr;

trait WithPerformanceDateResolution
{
    protected function resolvePerformanceDates(DateIntervalEnum $savedInterval, array $userSettings): array
    {
        $performanceDates = [null, null];

        if ($savedInterval === DateIntervalEnum::CUSTOM) {
            $rangeInterval = Arr::get($userSettings, 'range_interval', '');
            if ($rangeInterval) {
                $dates = explode('-', $rangeInterval);
                if (count($dates) === 2) {
                    $performanceDates = [$dates[0], $dates[1]];
                }
            }

            return $performanceDates;
        }

        if ($savedInterval === DateIntervalEnum::ALL) {
            return $performanceDates;
        }

        $intervalString = DashboardIntervalFilters::run($savedInterval);
        if (!$intervalString) {
            return $performanceDates;
        }

        $dates = explode('-', $intervalString);
        if (count($dates) !== 2) {
            return $performanceDates;
        }

        return [$dates[0], $dates[1]];
    }
}
