<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Helpers;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Carbon\Carbon;

class TimeSeriesPeriodCalculator
{
    public static function resolvePeriod(object $result, TimeSeriesFrequencyEnum $frequency): array
    {
        return match ($frequency) {
            TimeSeriesFrequencyEnum::QUARTERLY => [
                'periodFrom' => Carbon::create((int) $result->year, ((int) $result->quarter - 1) * 3 + 1)->startOfQuarter(),
                'periodTo'   => Carbon::create((int) $result->year, ((int) $result->quarter - 1) * 3 + 1)->endOfQuarter(),
                'period'     => $result->year . ' Q' . $result->quarter,
            ],
            TimeSeriesFrequencyEnum::MONTHLY => [
                'periodFrom' => Carbon::create((int) $result->year, (int) $result->month)->startOfMonth(),
                'periodTo'   => Carbon::create((int) $result->year, (int) $result->month)->endOfMonth(),
                'period'     => $result->year . '-' . str_pad($result->month, 2, '0', STR_PAD_LEFT),
            ],
            TimeSeriesFrequencyEnum::WEEKLY => [
                'periodFrom' => Carbon::create((int) $result->year)->week((int) $result->week)->startOfWeek(),
                'periodTo'   => Carbon::create((int) $result->year)->week((int) $result->week)->endOfWeek(),
                'period'     => $result->year . ' W' . str_pad($result->week, 2, '0', STR_PAD_LEFT),
            ],
            TimeSeriesFrequencyEnum::DAILY => [
                'periodFrom' => Carbon::parse($result->date)->startOfDay(),
                'periodTo'   => Carbon::parse($result->date)->endOfDay(),
                'period'     => Carbon::parse($result->date)->format('Y-m-d'),
            ],
            default => [
                'periodFrom' => Carbon::parse((int) $result->year . '-01-01'),
                'periodTo'   => Carbon::parse((int) $result->year . '-12-31'),
                'period'     => (string) $result->year,
            ],
        };
    }

    public static function getNonInvoicePeriods(
        TimeSeriesFrequencyEnum $frequency,
        string $from,
        string $to,
        array $processedPeriods
    ): array {
        $periods   = [];
        $startDate = Carbon::parse($from)->startOfDay();
        $endDate   = Carbon::parse($to)->endOfDay();

        [$current, $advanceMethod, $labelFn, $fromFn, $toFn] = self::getFrequencyIteratorConfig($frequency, $startDate);

        while ($current <= $endDate) {
            $period = $labelFn($current);

            if (!in_array($period, $processedPeriods)) {
                $periods[] = [
                    'from'   => $fromFn($current),
                    'to'     => $toFn($current),
                    'period' => $period,
                ];
            }

            $current->{$advanceMethod}();
        }

        return $periods;
    }

    private static function getFrequencyIteratorConfig(TimeSeriesFrequencyEnum $frequency, Carbon $startDate): array
    {
        return match ($frequency) {
            TimeSeriesFrequencyEnum::DAILY => [
                $startDate->copy(),
                'addDay',
                fn (Carbon $c) => $c->format('Y-m-d'),
                fn (Carbon $c) => $c->copy()->startOfDay(),
                fn (Carbon $c) => $c->copy()->endOfDay(),
            ],
            TimeSeriesFrequencyEnum::WEEKLY => [
                $startDate->copy()->startOfWeek(),
                'addWeek',
                fn (Carbon $c) => $c->isoFormat('GGGG') . ' W' . str_pad($c->isoWeek(), 2, '0', STR_PAD_LEFT),
                fn (Carbon $c) => $c->copy()->startOfWeek(),
                fn (Carbon $c) => $c->copy()->endOfWeek(),
            ],
            TimeSeriesFrequencyEnum::MONTHLY => [
                $startDate->copy()->startOfMonth(),
                'addMonth',
                fn (Carbon $c) => $c->year . '-' . str_pad($c->month, 2, '0', STR_PAD_LEFT),
                fn (Carbon $c) => $c->copy()->startOfMonth(),
                fn (Carbon $c) => $c->copy()->endOfMonth(),
            ],
            TimeSeriesFrequencyEnum::QUARTERLY => [
                $startDate->copy()->startOfQuarter(),
                'addQuarter',
                fn (Carbon $c) => $c->year . ' Q' . $c->quarter,
                fn (Carbon $c) => $c->copy()->startOfQuarter(),
                fn (Carbon $c) => $c->copy()->endOfQuarter(),
            ],
            default => [
                $startDate->copy()->startOfYear(),
                'addYear',
                fn (Carbon $c) => (string) $c->year,
                fn (Carbon $c) => $c->copy()->startOfYear(),
                fn (Carbon $c) => $c->copy()->endOfYear(),
            ],
        };
    }
}
