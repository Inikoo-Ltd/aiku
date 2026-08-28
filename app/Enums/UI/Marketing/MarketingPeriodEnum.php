<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Marketing;

use App\Enums\EnumHelperTrait;
use Illuminate\Support\Carbon;

enum MarketingPeriodEnum: string
{
    use EnumHelperTrait;

    case LAST_7 = 'last_7';
    case LAST_30 = 'last_30';
    case LAST_90 = 'last_90';
    case MONTH_TO_DATE = 'month_to_date';
    case YEAR_TO_DATE = 'year_to_date';
    case ALL_TIME = 'all_time';

    public static function labels(): array
    {
        return [
            self::LAST_7->value        => __('Last 7 days'),
            self::LAST_30->value       => __('Last 30 days'),
            self::LAST_90->value       => __('Last 90 days'),
            self::MONTH_TO_DATE->value => __('Month to date'),
            self::YEAR_TO_DATE->value  => __('Year to date'),
            self::ALL_TIME->value      => __('All time'),
        ];
    }

    /**
     * The inclusive start of the period, or null for all time. The end is always now: marketing never
     * asks about the future, and leaving it open keeps today's partial data visible.
     */
    public function startsAt(): ?Carbon
    {
        return match ($this) {
            self::LAST_7        => now()->subDays(7)->startOfDay(),
            self::LAST_30       => now()->subDays(30)->startOfDay(),
            self::LAST_90       => now()->subDays(90)->startOfDay(),
            self::MONTH_TO_DATE => now()->startOfMonth(),
            self::YEAR_TO_DATE  => now()->startOfYear(),
            self::ALL_TIME      => null,
        };
    }
}
