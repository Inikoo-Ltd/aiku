<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 22 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\Helpers\Period;

use App\Enums\EnumHelperTrait;
use Illuminate\Support\Carbon;

enum ComparePeriodEnum: string
{
    use EnumHelperTrait;

    case PREVIOUS_PERIOD = 'previous_period';
    case PREVIOUS_MONTH = 'previous_month';
    case PREVIOUS_YEAR = 'previous_year';
    case CUSTOM = 'custom';

    public static function labels(): array
    {
        return [
            self::PREVIOUS_PERIOD->value => __('Previous period'),
            self::PREVIOUS_MONTH->value  => __('Previous month'),
            self::PREVIOUS_YEAR->value   => __('Previous year'),
            self::CUSTOM->value          => __('Custom'),
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value];
    }

    /**
     * The period to compare with: the same number of days right before, the same dates a month or a
     * year earlier, or the custom dates. Null when the custom dates are missing or reversed.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function range(Carbon $from, Carbon $to, ?string $customFrom = null, ?string $customTo = null): ?array
    {
        $range = match ($this) {
            self::PREVIOUS_PERIOD => [
                $from->copy()->subDays((int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1)->startOfDay(),
                $from->copy()->subDay()->endOfDay(),
            ],
            self::PREVIOUS_MONTH => [$from->copy()->subMonthNoOverflow()->startOfDay(), $to->copy()->subMonthNoOverflow()->endOfDay()],
            self::PREVIOUS_YEAR  => [$from->copy()->subYearNoOverflow()->startOfDay(), $to->copy()->subYearNoOverflow()->endOfDay()],
            self::CUSTOM         => $customFrom && $customTo ? [self::date($customFrom)?->startOfDay(), self::date($customTo)?->endOfDay()] : [null, null],
        };

        return $range[0] && $range[1] && $range[0]->lte($range[1]) ? $range : null;
    }

    private static function date(string $date): ?Carbon
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? Carbon::createFromFormat('Y-m-d', $date) : null;
    }
}
