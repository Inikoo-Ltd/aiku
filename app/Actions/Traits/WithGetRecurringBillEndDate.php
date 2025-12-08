<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Traits;

use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

trait WithGetRecurringBillEndDate
{
    public function getEndDate(Carbon $startDate, array $setting): Carbon
    {
        return match (Arr::get($setting, 'type')) {
            'weekly' => $this->getEndDateWeekly($startDate, $setting),
            default => $this->getEndDateMonthly($startDate, $setting),
        };
    }

    public function getEndDateMonthly(Carbon $startDate, array $setting): Carbon
    {

        $endDayOfMonth = $setting['day'];
        if ($endDayOfMonth == 'last_day') {

            $now = Carbon::now();
            $endOfMonth = $now->endOfMonth();
            $daysUntilEndOfMonth = $now->diffInDays($endOfMonth);

            if ($daysUntilEndOfMonth < 7) {
                return Carbon::now()->addDays(7)->endOfMonth()->startOfDay();
            } else {
                return Carbon::now()->endOfMonth()->startOfDay();
            }

        }
        $endDate = now()->day($endDayOfMonth);
        if (now()->gte($endDate)) {
            $endDate = $endDate->addMonth();
        }

        if (Arr::get($setting, 'is_weekdays') and $endDate->isWeekend()) {
            $endDate = $endDate->nextWeekday();
        }

        return $endDate;
    }

    public function getEndDateWeekly(Carbon $startDate, array $setting): Carbon
    {
        $daysOfWeek = [
            'Sunday' => CarbonInterface::SUNDAY,
            'Monday' => CarbonInterface::MONDAY,
            'Tuesday' => CarbonInterface::TUESDAY,
            'Wednesday' => CarbonInterface::WEDNESDAY,
            'Thursday' => CarbonInterface::THURSDAY,
            'Friday' => CarbonInterface::FRIDAY,
            'Saturday' => CarbonInterface::SATURDAY,
        ];

        $endDayOfWeek = $daysOfWeek[$setting['day']];

        return $startDate->copy()->next($endDayOfWeek);
    }
}
