<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 12:22:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\TimeSeries;

use App\Enums\EnumHelperTrait;
use Illuminate\Support\Carbon;

enum TimeSeriesFrequencyEnum: string
{
    use EnumHelperTrait;

    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case YEARLY = 'yearly';

    public static function labels(): array
    {
        return [
            'daily'     => __('Daily'),
            'weekly'    => __('Weekly'),
            'monthly'   => __('Monthly'),
            'quarterly' => __('Quarterly'),
            'yearly'    => __('Yearly'),
        ];
    }

    public function singleLetter(): string
    {
        return match ($this) {
            self::DAILY => 'D',
            self::WEEKLY => 'W',
            self::MONTHLY => 'M',
            self::QUARTERLY => 'Q',
            self::YEARLY => 'Y',
        };
    }

    /**
     * Earliest possible start of a period of this frequency that is still running at the given date.
     */
    public function earliestPeriodStart(Carbon $date): Carbon
    {
        return match ($this) {
            self::DAILY => $date->copy()->subDay(),
            self::WEEKLY => $date->copy()->subWeek(),
            self::MONTHLY => $date->copy()->subMonth(),
            self::QUARTERLY => $date->copy()->subQuarter(),
            self::YEARLY => $date->copy()->subYear(),
        };
    }

}
