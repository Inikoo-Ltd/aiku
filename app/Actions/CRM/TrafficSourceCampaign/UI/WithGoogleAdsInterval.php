<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\UI;

use App\Enums\DateIntervals\DateIntervalEnum;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The period both Google Ads screens read their daily figures over.
 *
 * Intervals shorter than a day are left out, unlike the tickets listing: Google reports spend and
 * conversions by day in the account's own time zone, so an hourly window would either double count
 * a day or show nothing at all depending on which side of midnight the account sits.
 */
trait WithGoogleAdsInterval
{
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

        return array_key_exists($interval, $this->intervalOptions())
            ? DateIntervalEnum::from($interval)
            : DateIntervalEnum::ONE_MONTH;
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
}
