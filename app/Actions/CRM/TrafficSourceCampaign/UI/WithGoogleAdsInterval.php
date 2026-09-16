<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\UI;

use App\Enums\DateIntervals\DateIntervalEnum;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The period both Google Ads screens read their daily figures over, and the period before it that
 * the figures are compared against.
 *
 * Intervals shorter than a day are left out, unlike the tickets listing: Google reports spend and
 * conversions by day in the account's own time zone, so an hourly window would either double count
 * a day or show nothing at all depending on which side of midnight the account sits.
 *
 * A custom range travels as `period=ctm&from=Y-m-d&to=Y-m-d`. A range that does not parse, runs
 * backwards or spans more than two years falls back to the default period rather than erroring, so
 * a mistyped URL still lands on a working page.
 */
trait WithGoogleAdsInterval
{
    private const string CUSTOM_PERIOD = 'ctm';

    private const int CUSTOM_PERIOD_MAX_DAYS = 731;

    /**
     * @return array<string, string>
     */
    public function intervalOptions(): array
    {
        $labels = DateIntervalEnum::labels();

        return [
            'tdy' => $labels['tdy'],
            'ld'  => $labels['ld'],
            '1w'  => $labels['1w'],
            'mtd' => $labels['mtd'],
            '1m'  => $labels['1m'],
            'lm'  => $labels['lm'],
            '1q'  => $labels['1q'],
            '1y'  => $labels['1y'],
            'all' => $labels['all'],
        ];
    }

    public function interval(): DateIntervalEnum
    {
        $interval = (string) request()->input('period');

        if ($interval === self::CUSTOM_PERIOD && $this->customRange()) {
            return DateIntervalEnum::CUSTOM;
        }

        return array_key_exists($interval, $this->intervalOptions())
            ? DateIntervalEnum::from($interval)
            : DateIntervalEnum::ONE_MONTH;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function customRange(): ?array
    {
        if ((string) request()->input('period') !== self::CUSTOM_PERIOD) {
            return null;
        }

        try {
            $from = Carbon::createFromFormat('Y-m-d', (string) request()->input('from'))->startOfDay();
            $to   = Carbon::createFromFormat('Y-m-d', (string) request()->input('to'))->endOfDay();
        } catch (Throwable) {
            return null;
        }

        if ($from->gt($to) || $from->diffInDays($to) > self::CUSTOM_PERIOD_MAX_DAYS) {
            return null;
        }

        return [$from, $to];
    }

    public function wherePeriod($query, string $column)
    {
        $custom = $this->customRange();

        return $custom
            ? $query->whereBetween($column, $custom)
            : $this->interval()->wherePeriod($query, $column);
    }

    /**
     * The chosen period as two dates, for the queries that go to Google rather than to Postgres.
     *
     * Read back out of the same `wherePeriod` the tables use instead of restating its rules here: two
     * copies of "what does last month mean" drift apart, and the first anyone would know of it is a
     * search terms report that disagrees with the totals printed above it.
     *
     * "All" has no bounds to read, and Google keeps search terms for a limited window anyway, so it
     * falls back to the widest range worth asking for.
     *
     * @return array{0: string, 1: string}
     */
    public function intervalDates(int $fallbackDays = 90): array
    {
        if ($custom = $this->customRange()) {
            return [$custom[0]->toDateString(), $custom[1]->toDateString()];
        }

        $probe = DB::query()->from('probe');
        $this->interval()->wherePeriod($probe, 'date');

        $bindings = $probe->getBindings();

        if (count($bindings) < 2) {
            return [now()->subDays($fallbackDays)->toDateString(), now()->toDateString()];
        }

        return [
            Carbon::parse($bindings[0])->toDateString(),
            Carbon::parse($bindings[1])->toDateString(),
        ];
    }

    public function periodLabel(): string
    {
        if ($custom = $this->customRange()) {
            return $this->rangeLabel($custom[0], $custom[1]);
        }

        return $this->intervalOptions()[$this->interval()->value];
    }

    public function isComparing(): bool
    {
        return request()->boolean('compare') && $this->comparisonRange() !== null;
    }

    /**
     * The period the chosen one is measured against. Month-shaped periods compare with the month
     * before, cut to the same day where the month is still running, so "this month so far" is set
     * against the same days of last month rather than against a mix of two months. Everything else
     * compares with the window of the same length that ended the day before it began. "All" has no
     * before.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function comparisonRange(): ?array
    {
        $interval = $this->interval();

        if ($interval === DateIntervalEnum::ALL) {
            return null;
        }

        [$fromDate, $toDate] = $this->intervalDates();

        $from = Carbon::parse($fromDate)->startOfDay();
        $to   = Carbon::parse($toDate)->endOfDay();

        return match ($interval) {
            DateIntervalEnum::MONTH_TO_DAY => [
                $from->copy()->subMonthNoOverflow()->startOfMonth(),
                $to->copy()->subMonthNoOverflow()->endOfDay(),
            ],
            DateIntervalEnum::LAST_MONTH => [
                $from->copy()->subMonthNoOverflow()->startOfMonth(),
                $from->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            default => [
                $from->copy()->subDays($this->daysBetween($from, $to))->startOfDay(),
                $from->copy()->subDay()->endOfDay(),
            ],
        };
    }

    public function wherePreviousPeriod($query, string $column)
    {
        $range = $this->comparisonRange();

        return $range
            ? $query->whereBetween($column, $range)
            : $query->whereRaw('1 = 0');
    }

    public function wherePeriodOrPrevious($query, string $column, bool $previous)
    {
        return $previous
            ? $this->wherePreviousPeriod($query, $column)
            : $this->wherePeriod($query, $column);
    }

    public function comparisonLabel(): ?string
    {
        $range = $this->comparisonRange();

        return $range ? $this->rangeLabel($range[0], $range[1]) : null;
    }

    /**
     * Everything a page needs to draw the period controls.
     *
     * @return array{periods: array<string, string>, period: string, period_label: string, custom_range: array{from: string, to: string}|null, compare: bool, comparison_label: string|null}
     */
    public function periodProps(): array
    {
        $custom = $this->customRange();

        return [
            'periods'          => $this->intervalOptions(),
            'period'           => $this->interval()->value,
            'period_label'     => $this->periodLabel(),
            'custom_range'     => $custom ? ['from' => $custom[0]->toDateString(), 'to' => $custom[1]->toDateString()] : null,
            'compare'          => $this->isComparing(),
            'comparison_label' => $this->comparisonLabel(),
        ];
    }

    private function daysBetween(Carbon $from, Carbon $to): int
    {
        return (int) round($from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay())) + 1;
    }

    private function rangeLabel(Carbon $from, Carbon $to): string
    {
        return $from->format('j M Y').' '.__('to').' '.$to->format('j M Y');
    }
}
