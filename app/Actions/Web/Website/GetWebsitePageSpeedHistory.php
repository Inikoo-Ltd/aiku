<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Sep 2026 10:00:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\Web\Webpage\GetWebpagePageSpeed;
use App\Actions\Web\Webpage\StoreWebpagePageSpeedTimeSeriesRecord;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Web\Website;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetWebsitePageSpeedHistory
{
    use AsAction;

    public const int DAYS = 90;

    private const int DAILY_MAX_DAYS = 92;

    /**
     * @return array{frequency: string, start_date: string, end_date: string, measured_webpages: int, last_measured_on: string|null, history: array<int, array<string, mixed>>}
     */
    public function handle(Website $website, int $days = self::DAYS): array
    {
        $endDate   = now()->endOfDay();
        $startDate = $endDate->copy()->subDays($days - 1)->startOfDay();
        $frequency = $days > self::DAILY_MAX_DAYS
            ? TimeSeriesFrequencyEnum::WEEKLY
            : TimeSeriesFrequencyEnum::DAILY;

        $history = $this->history($website, $frequency, $startDate, $endDate);

        return [
            'frequency'         => $frequency->value,
            'start_date'        => $startDate->toDateString(),
            'end_date'          => $endDate->toDateString(),
            'measured_webpages' => $this->measuredWebpages($website, $startDate, $endDate),
            'last_measured_on'  => $history ? end($history)['date'] : null,
            'history'           => $history,
        ];
    }

    /**
     * Every measured webpage of the website weighs the same, and a point only counts the webpages
     * measured on that day, so a day the crawl did not reach a page does not drag its score down.
     *
     * @return array<int, array<string, mixed>>
     */
    private function history(Website $website, TimeSeriesFrequencyEnum $frequency, Carbon $startDate, Carbon $endDate): array
    {
        $query = $this->measuredRecords($website, $startDate, $endDate)
            ->where('records.frequency', $frequency->singleLetter())
            ->groupBy('records.from')
            ->orderBy('records.from')
            ->selectRaw('records."from" as measured_on');

        foreach (GetWebpagePageSpeed::STRATEGIES as $strategy) {
            $query->selectRaw('count(records.'.StoreWebpagePageSpeedTimeSeriesRecord::column($strategy, 'performance').") as {$strategy}_webpages");

            foreach (StoreWebpagePageSpeedTimeSeriesRecord::SCORES as $score) {
                $column = StoreWebpagePageSpeedTimeSeriesRecord::column($strategy, $score);
                $query->selectRaw("round(avg(records.$column)) as $column");
            }
        }

        return $query->get()->map(fn ($row) => [
            'date'     => Carbon::parse($row->measured_on)->toDateString(),
            'webpages' => $this->webpagesByStrategy($row),
            ...$this->scoresByStrategy($row),
        ])->values()->all();
    }

    private function measuredWebpages(Website $website, Carbon $startDate, Carbon $endDate): int
    {
        return $this->measuredRecords($website, $startDate, $endDate)
            ->where('records.frequency', TimeSeriesFrequencyEnum::DAILY->singleLetter())
            ->distinct()
            ->count('series.webpage_id');
    }

    private function measuredRecords(Website $website, Carbon $startDate, Carbon $endDate): Builder
    {
        return DB::table('webpage_time_series_records as records')
            ->join('webpage_time_series as series', 'series.id', '=', 'records.webpage_time_series_id')
            ->join('webpages', 'webpages.id', '=', 'series.webpage_id')
            ->where('webpages.website_id', $website->id)
            ->whereNull('webpages.deleted_at')
            ->where('records.to', '>=', $startDate)
            ->where('records.from', '<=', $endDate)
            ->where(function (Builder $query) {
                foreach (StoreWebpagePageSpeedTimeSeriesRecord::columns() as $column) {
                    $query->orWhereNotNull("records.$column");
                }
            });
    }

    /**
     * @return array<string, int>
     */
    private function webpagesByStrategy(object $row): array
    {
        $webpages = [];

        foreach (GetWebpagePageSpeed::STRATEGIES as $strategy) {
            $webpages[$strategy] = (int)$row->{$strategy.'_webpages'};
        }

        return $webpages;
    }

    /**
     * @return array<string, array<string, int|null>>
     */
    private function scoresByStrategy(object $row): array
    {
        $scoresByStrategy = [];

        foreach (GetWebpagePageSpeed::STRATEGIES as $strategy) {
            foreach (StoreWebpagePageSpeedTimeSeriesRecord::SCORES as $score) {
                $value = $row->{StoreWebpagePageSpeedTimeSeriesRecord::column($strategy, $score)};

                $scoresByStrategy[$strategy][$score] = $value === null ? null : (int)$value;
            }
        }

        return $scoresByStrategy;
    }
}
