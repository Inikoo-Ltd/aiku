<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Jun 2023 23:19:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\Period;

use App\Enums\EnumHelperTrait;
use Illuminate\Support\Carbon;

enum PeriodEnum: string
{
    use EnumHelperTrait;

    case Day     = 'day';
    case WEEK    = 'week';
    case MONTH   = 'month';
    case QUARTER = 'quarter';
    case YEAR    = 'year';

    public static function labels(): array
    {
        return [
            'day'     => __('Day'),
            'week'    => __('Week'),
            'month'   => __('Month'),
            'quarter' => __('Quarter'),
            'year'    => __('Year')
        ];
    }

    public static function date(): array
    {
        $now     = now();
        $quarter = ceil($now->format('n') / 3);

        return [
            'day'     => $now->format('Ymd'),
            'week'    => $now->format('oW'),
            'month'   => $now->format('Ym'),
            'quarter' => $now->format('Y') . 'Q' . $quarter,
            'year'    => $now->format('Y')
        ];
    }


    public static function toDateRange(array $period): array
    {
        $type  = array_key_first($period);
        $value = $period[$type];

        return match ($type) {
            'day' => self::dayRange($value),
            'week' => self::weekRange($value),
            'month' => self::monthRange($value),
            'quarter' => self::quarterRange($value),
            'year' => self::yearRange($value),
            default => self::dayRange($value),
        };
    }

    protected static function dayRange(string $value): array
    {
        $date = Carbon::createFromFormat('Ymd', $value);
        return [
            $date->toDateString(),
            $date->toDateString(),
        ];
    }

    protected static function weekRange(string $value): array
    {
        $year = substr($value, 0, 4);
        $week = substr($value, 4, 2);

        $start = Carbon::now()
            ->setISODate((int) $year, (int) $week)
            ->startOfWeek(Carbon::MONDAY);

        $end = (clone $start)->endOfWeek(Carbon::SUNDAY);

        return [
            $start->toDateString(),
            $end->toDateString(),
        ];
    }

    protected static function monthRange(string $value): array
    {
        $year  = substr($value, 0, 4);
        $month = substr($value, 4, 2);

        $start = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfMonth();
        $end   = (clone $start)->endOfMonth();

        return [
            $start->toDateString(),
            $end->toDateString(),
        ];
    }

    protected static function quarterRange(string $value): array
    {
        preg_match('/(\d{4})Q([1-4])/', $value, $matches);

        $year    = (int) $matches[1];
        $quarter = (int) $matches[2];

        $startMonth = (($quarter - 1) * 3) + 1;

        $start = Carbon::createFromDate($year, $startMonth, 1)->startOfMonth();
        $end   = (clone $start)->addMonths(2)->endOfMonth();

        return [
            $start->toDateString(),
            $end->toDateString(),
        ];
    }

    protected static function yearRange(string $value): array
    {
        $start = Carbon::createFromDate((int) $value, 1, 1)->startOfYear();
        $end   = (clone $start)->endOfYear();

        return [
            $start->toDateString(),
            $end->toDateString(),
        ];
    }
}
