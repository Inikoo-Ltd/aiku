<?php

/*
 * Author: YudhistiraA <aryarajasa0@gmail.com>
 * Copyright (c) 2026, YudhistiraA
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Web\Webpage\Hydrators\WebpageHydrateTimeSeriesNumberRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\TimeSeriesPeriodCalculator;
use App\Models\Web\Webpage;
use App\Models\Web\WebpageTimeSeries;
use App\Traits\UpsertsTimeSeriesRecords;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreWebpagePageSpeedTimeSeriesRecord
{
    use AsAction;
    use UpsertsTimeSeriesRecords;

    public const array SCORES = [
        'performance'    => 'performance',
        'accessibility'  => 'accessibility',
        'best-practices' => 'best_practices',
        'seo'            => 'seo',
    ];

    public static function column(string $strategy, string $score): string
    {
        return "pagespeed_{$strategy}_$score";
    }

    /**
     * @return array<int, string>
     */
    public static function columns(): array
    {
        $columns = [];

        foreach (GetWebpagePageSpeed::STRATEGIES as $strategy) {
            foreach (self::SCORES as $score) {
                $columns[] = self::column($strategy, $score);
            }
        }

        return $columns;
    }

    public static function recordedKey(Webpage $webpage, string $strategy): string
    {
        return "webpage-pagespeed-recorded:$webpage->id:$strategy";
    }

    public static function isRecorded(Webpage $webpage, array $result): bool
    {
        $strategy  = Arr::get($result, 'strategy');
        $fetchedAt = Arr::get($result, 'fetched_at');

        if (!$strategy || !$fetchedAt) {
            return false;
        }

        return cache()->get(self::recordedKey($webpage, $strategy)) === $fetchedAt;
    }

    public function handle(Webpage $webpage, array $result): void
    {
        $strategy = Arr::get($result, 'strategy');

        if (!in_array($strategy, GetWebpagePageSpeed::STRATEGIES, true)) {
            return;
        }

        $measuredAt = Carbon::parse(Arr::get($result, 'fetched_at') ?? now())->setTimezone(config('app.timezone'));

        ['period' => $period, 'periodFrom' => $periodFrom, 'periodTo' => $periodTo] = TimeSeriesPeriodCalculator::resolvePeriodFromDate($measuredAt, TimeSeriesFrequencyEnum::DAILY);

        $dailyTimeSeries = WebpageTimeSeries::where('webpage_id', $webpage->id)->where('frequency', TimeSeriesFrequencyEnum::DAILY->value)->first();

        if (!$dailyTimeSeries) {
            $dailyTimeSeries = $webpage->timeSeries()->create(['frequency' => TimeSeriesFrequencyEnum::DAILY]);
        }

        $this->upsertTimeSeriesRecords($dailyTimeSeries, [
            [
                'webpage_time_series_id' => $dailyTimeSeries->id,
                'period'                 => $period,
                'frequency'              => TimeSeriesFrequencyEnum::DAILY->singleLetter(),
                'from'                   => $periodFrom,
                'to'                     => $periodTo,
                ...$this->strategyScores($strategy, $result),
            ]
        ], ['webpage_time_series_id', 'period', 'frequency']);

        cache()->put(self::recordedKey($webpage, $strategy), Arr::get($result, 'fetched_at'), now()->addHours(GetWebpagePageSpeed::RESULT_TTL_HOURS));

        WebpageHydrateTimeSeriesNumberRecords::dispatch($dailyTimeSeries->id);

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($frequency === TimeSeriesFrequencyEnum::DAILY) {
                continue;
            }

            [$from, $to] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $period, $period);

            ProcessWebpageTimeSeriesRecords::dispatch($webpage->id, $frequency, $from, $to);
        }
    }

    /**
     * @return array<string, int|null>
     */
    private function strategyScores(string $strategy, array $result): array
    {
        $scores = collect(Arr::get($result, 'scores', []))->pluck('score', 'key');

        $strategyScores = [];

        foreach (self::SCORES as $key => $score) {
            $strategyScores[self::column($strategy, $score)] = $scores->get($key);
        }

        return $strategyScores;
    }
}
