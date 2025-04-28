<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 12 Mar 2023 03:29:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\DateIntervals;

use App\Enums\EnumHelperTrait;

enum DateIntervalEnum: string
{
    use EnumHelperTrait;

    case ALL = 'all'; // All must be the first value
    case ONE_YEAR = '1y';
    case ONE_QUARTER = '1q';
    case ONE_MONTH = '1m';
    case ONE_WEEK = '1w';
    case THREE_DAYS = '3d';
    case YEAR_TO_DAY = 'ytd';
    case QUARTER_TO_DAY = 'qtd';
    case MONTH_TO_DAY = 'mtd';
    case WEEK_TO_DAY = 'wtd';
    case TODAY = 'tdy';
    case LAST_MONTH = 'lm';
    case LAST_WEEK = 'lw';
    case YESTERDAY = 'ld';


    public static function labels(): array
    {
        return [
            'all' => __('All'),
            '1y'  => __('1 Year'),
            '1q'  => __('1 Quarter'),
            '1m'  => __('1 Month'),
            '1w'  => __('1 Week'),
            '3d'  => __('3 Days'),
            'ytd' => __('Year to Day'),
            'qtd' => __('Quarter to Day'),
            'mtd' => __('Month to Day'),
            'wtd' => __('Week to Day'),
            'tdy' => __('Today'),
            'lm'  => __('Last Month'),
            'lw'  => __('Last Week'),
            'ld'  => __('Yesterday'),
        ];
    }

    public static function shortLabels(): array
    {
        return [
            'all' => __('All'),
            '1y'  => __('1y'),
            '1q'  => __('1q'),
            '1m'  => __('1m'),
            '1w'  => __('1w'),
            '3d'  => __('3d'),
            'ytd' => __('YTD'),
            'qtd' => __('QTD'),
            'mtd' => __('MTD'),
            'wtd' => __('WTD'),
            'tdy' => __('Today'),
            'lm'  => __('LM'),
            'lw'  => __('LW'),
            'ld'  => __('YDAY'),

        ];
    }


    public static function lastYearValues(): array
    {
        $intervals = self::values();
        unset($intervals[0]); // This only work if all is the first value

        return $intervals;
    }


    public function wherePeriod($query, $column)
    {
        return match ($this->value) {
            '1y' => $query->whereBetween($column, [now()->subYear()->startOfDay(), now()->endOfDay()]),
            '1q' => $query->whereBetween($column, [now()->subQuarter()->startOfDay(), now()->endOfDay()]),
            '1m' => $query->whereBetween($column, [now()->subMonth()->startOfDay(), now()->endOfDay()]),
            '1w' => $query->whereBetween($column, [now()->subWeek()->startOfDay(), now()->endOfDay()]),
            '3d' => $query->whereBetween($column, [now()->subDays(3)->startOfDay(), now()->endOfDay()]),
            'ytd' => $query->whereBetween($column, [now()->startOfYear()->startOfDay(), now()->endOfDay()]),
            'tdy' => $query->whereBetween($column, [now()->startOfDay(), now()->endOfDay()]),
            'qtd' => $query->whereBetween($column, [now()->startOfQuarter()->startOfDay(), now()->endOfDay()]),
            'mtd' => $query->whereBetween($column, [now()->startOfMonth()->startOfDay(), now()->endOfDay()]),
            'wtd' => $query->whereBetween($column, [now()->startOfWeek()->startOfDay(), now()->endOfDay()]),
            'lm' => $query->whereBetween($column, [now()->subMonth()->startOfMonth()->startOfDay(), now()->subMonth()->endOfMonth()->endOfDay()]),
            'lw' => $query->whereBetween($column, [now()->subWeek()->startOfWeek()->startOfDay(), now()->subWeek()->endOfWeek()->endOfDay()]),
            'ld' => $query->whereBetween($column, [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()]),
            default => $query,
        };
    }

    public function whereLastYearPeriod($query, $column)
    {
        return match ($this->value) {
            '1y' => $query->whereBetween($column, [now()->subYears(2)->startOfDay(), now()->subYear()->endOfDay()]),
            '1q' => $query->whereBetween($column, [now()->subQuarter()->subYear()->startOfDay(), now()->subYear()->endOfDay()]),
            '1m' => $query->whereBetween($column, [now()->subMonth()->subYear()->startOfDay(), now()->subYear()->endOfDay()]),
            '1w' => $query->whereBetween($column, [now()->subWeek()->subYear()->startOfDay(), now()->subYear()->endOfDay()]),
            '3d' => $query->whereBetween($column, [now()->subDays(3)->subYear()->startOfDay(), now()->subYear()->endOfDay()]),
            'ytd' => $query->whereBetween($column, [now()->startOfYear()->subYear()->startOfDay(), now()->subYear()->endOfDay()]),
            'tdy' => $query->whereBetween($column, [now()->startOfDay()->subYear()->startOfDay(), now()->subYear()->endOfDay()]),
            'qtd' => $query->whereBetween($column, [now()->startOfQuarter()->subYear()->startOfDay(), now()->subYear()->endOfDay()]),
            'mtd' => $query->whereBetween($column, [now()->startOfMonth()->subYear()->startOfDay(), now()->subYear()->endOfDay()]),
            'wtd' => $query->whereBetween($column, [now()->startOfWeek()->subYear()->startOfDay(), now()->subYear()->endOfDay()]),
            'lm' => $query->whereBetween($column, [now()->subMonth()->startOfMonth()->subYear()->startOfDay(), now()->subMonth()->endOfMonth()->subYear()->endOfDay()]),
            'lw' => $query->whereBetween($column, [now()->subWeek()->startOfWeek()->subYear()->startOfDay(), now()->subWeek()->endOfWeek()->subYear()->endOfDay()]),
            'ld' => $query->whereBetween($column, [now()->subDay()->startOfDay()->subYear()->startOfDay(), now()->subDay()->endOfDay()->subYear()->endOfDay()]),
            default => $query,
        };
    }

    public static function allExceptHistorical(): array
    {
        return array_map(
            fn ($case) => $case->value,
            array_values(array_filter(
                self::cases(),
                fn ($case) => !in_array($case, [self::YESTERDAY, self::LAST_WEEK, self::LAST_MONTH])
            ))
        );
    }


}
