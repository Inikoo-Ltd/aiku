<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Helpers\Forecast\ForecastWithTimesFm;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Forecasts each active SKO's demand with Google's TimesFM into org_stock_stats.demand_forecast,
 * which OrgStockHydrateOutOfStockForecast then uses for days of cover, the stock-out date and the
 * quantity to reorder. TimesFM reads demand per SKO better than the old smoothing but runs short
 * when demand turns, so each night it also forecasts the last six weeks from six weeks ago and
 * every organisation's forecasts are scaled by what was really dispatched against that. One-off
 * orders are cut down before forecasting and no forecast may exceed the item's busiest six weeks:
 * without these, a single 500-unit order or a long silence after a few sales made the forecast run
 * away on thin sellers (1 Oct 2026, rolled back). Backtested
 * on production dispatches (20 Aug to 30 Sep 2026, 3,000 SKOs): 0.52 of demand off per SKO and
 * 2% short overall, against 0.74 and 6% over for the old method. Each week it also keeps what it
 * and the old method expected for the next 42 days, to check against what was then dispatched.
 */
class ForecastOrgStockDemand
{
    use AsAction;

    public string $commandSignature = 'hydrate:org-stock-demand-forecast';

    private const int HISTORY_WEEKS = 156;

    private const int MINIMUM_HISTORY_WEEKS = 8;

    private const int WEEKS_AHEAD = 8;

    private const int CALIBRATION_WEEKS = 6;

    private const float MINIMUM_CORRECTION = 0.75;

    private const float MAXIMUM_CORRECTION = 1.5;

    private const int COMPARISON_DAYS = 42;

    private const int RECORDS_KEPT = 12;

    private const int SKOS_PER_BATCH = 5000;

    /**
     * @return int SKOs forecast
     */
    public function handle(?Carbon $today = null): int
    {
        $today       = ($today ?? now('UTC'))->copy()->startOfDay();
        $rows        = [];
        $calibration = [];
        $orgStocks   = DB::table('org_stocks')
            ->join('org_stock_stats', 'org_stock_stats.org_stock_id', '=', 'org_stocks.id')
            ->where('org_stocks.state', OrgStockStateEnum::ACTIVE->value)
            ->select('org_stocks.id', 'org_stocks.organisation_id', 'org_stocks.quantity_available', 'org_stock_stats.predicted_daily_usage', DB::raw("org_stock_stats.demand_forecast->'record' as record"))
            ->orderBy('org_stocks.id');

        $completed = true;
        $orgStocks->chunk(self::SKOS_PER_BATCH, function ($batch) use ($today, &$rows, &$calibration, &$completed) {
            $histories = $this->weeklyDispatches($batch->pluck('id')->all(), $today);
            if (!$histories) {
                return true;
            }
            $cleaned              = array_map(fn (array $weeks) => $this->withoutOneOffs($weeks), $histories);
            $calibrationHistories = $this->calibrationHistories($cleaned);
            $result               = ForecastWithTimesFm::run($cleaned, self::WEEKS_AHEAD);
            $earlier              = $calibrationHistories ? ForecastWithTimesFm::run($calibrationHistories, self::CALIBRATION_WEEKS) : ['deciles' => []];
            if ($result === null || $earlier === null) {
                return $completed = false;
            }

            $byId = $batch->keyBy('id');
            foreach ($earlier['deciles'] as $orgStockId => $deciles) {
                $organisationId                                  = $byId[$orgStockId]->organisation_id;
                $calibration[$organisationId]['forecast']        = ($calibration[$organisationId]['forecast'] ?? 0) + array_sum(ForecastWithTimesFm::expectedValues($deciles));
                $calibration[$organisationId]['dispatched']      = ($calibration[$organisationId]['dispatched'] ?? 0) + array_sum(array_slice($histories[$orgStockId], -self::CALIBRATION_WEEKS));
            }

            foreach ($byId->only(array_keys($result['deciles'])) as $orgStockId => $orgStock) {
                $weeks = array_map(
                    fn (float $expected, array $step) => [round($expected, 3), round(((max(0.0, (float) $step[8]) - max(0.0, (float) $step[0])) / 2.563) ** 2, 3)],
                    ForecastWithTimesFm::expectedValues($result['deciles'][$orgStockId]),
                    $result['deciles'][$orgStockId]
                );
                $rows[$orgStockId] = [
                    'organisation_id'    => $orgStock->organisation_id,
                    'version'            => $result['version'],
                    'from'               => $today->toDateString(),
                    'weeks'              => $weeks,
                    'quantity_available' => (float) $orgStock->quantity_available,
                    'ceiling'            => $this->busiestSixWeeks($cleaned[$orgStockId]),
                    'live_daily_usage'   => $orgStock->predicted_daily_usage,
                    'record'             => json_decode($orgStock->record ?? '[]', true) ?: [],
                ];
            }

            return true;
        });

        if (!$completed) {
            return 0;
        }

        $corrections = array_map(
            fn (array $sums) => $sums['forecast'] > 0 ? round(min(self::MAXIMUM_CORRECTION, max(self::MINIMUM_CORRECTION, $sums['dispatched'] / $sums['forecast'])), 3) : 1.0,
            $calibration
        );

        foreach (array_chunk($rows, 1000, true) as $chunk) {
            $this->store(array_map(fn (array $row) => $this->demandForecast($row, $corrections[$row['organisation_id']] ?? 1.0, $today), $chunk));
        }

        return count($rows);
    }

    /**
     * @param  array<string, mixed>  $row
     *
     * @return array<string, mixed>
     */
    private function demandForecast(array $row, float $correction, Carbon $today): array
    {
        $sixWeeks = array_sum(array_column(array_slice($row['weeks'], 0, self::CALIBRATION_WEEKS), 0)) * $correction;
        $scale    = $sixWeeks > $row['ceiling'] && $sixWeeks > 0 ? $correction * $row['ceiling'] / $sixWeeks : $correction;
        $weeks    = array_map(fn (array $week) => [round($week[0] * $scale, 3), round($week[1] * $scale ** 2, 3)], $row['weeks']);

        return [
            'version'       => $row['version'],
            'from'          => $row['from'],
            'correction'    => $correction,
            'capped'        => $scale < $correction,
            'weeks'         => $weeks,
            'days_of_cover' => $this->daysOfCover($row['quantity_available'], $weeks),
            'record'        => $this->record($row['record'], $weeks, $row['live_daily_usage'], $today),
        ];
    }

    /**
     * A week far above the item's normal one is a one-off order (a customer clearing a pallet, a
     * partner restocking): it is cut down to three times the median selling week before forecasting,
     * so one order does not read as a new level of demand.
     *
     * @param  list<float>  $weeks
     *
     * @return list<float>
     */
    private function withoutOneOffs(array $weeks): array
    {
        $selling = array_values(array_filter($weeks, fn (float $units) => $units > 0));
        if (count($selling) < 3) {
            return $weeks;
        }
        sort($selling);
        $middle = intdiv(count($selling), 2);
        $median = count($selling) % 2 ? $selling[$middle] : ($selling[$middle - 1] + $selling[$middle]) / 2;

        return array_map(fn (float $units) => min($units, 3 * $median), $weeks);
    }

    /**
     * The most the item has sold in any six weeks running: the forecast of the next six weeks is
     * never allowed above it.
     *
     * @param  list<float>  $weeks
     */
    private function busiestSixWeeks(array $weeks): float
    {
        if (count($weeks) <= self::CALIBRATION_WEEKS) {
            return array_sum($weeks);
        }

        $busiest = $running = array_sum(array_slice($weeks, 0, self::CALIBRATION_WEEKS));
        for ($week = self::CALIBRATION_WEEKS; $week < count($weeks); $week++) {
            $running += $weeks[$week] - $weeks[$week - self::CALIBRATION_WEEKS];
            $busiest  = max($busiest, $running);
        }

        return $busiest;
    }

    /**
     * Each history without its last six weeks, when enough is left to forecast from.
     *
     * @param  array<int, list<float>>  $histories
     *
     * @return array<int, list<float>>
     */
    private function calibrationHistories(array $histories): array
    {
        return array_filter(
            array_map(fn (array $weeks) => array_slice($weeks, 0, -self::CALIBRATION_WEEKS), $histories),
            fn (array $weeks) => count($weeks) >= self::MINIMUM_HISTORY_WEEKS && array_sum($weeks) > 0
        );
    }

    /**
     * Units dispatched per week, the last week ending yesterday, from each SKO's first dispatch.
     *
     * @return array<int, list<float>> org stock id => units per week, oldest first
     */
    private function weeklyDispatches(array $orgStockIds, Carbon $today): array
    {
        $rows = DB::table('delivery_note_items')
            ->whereIn('org_stock_id', $orgStockIds)
            ->where('quantity_dispatched', '>', 0)
            ->where('created_at', '>=', $today->copy()->subWeeks(self::HISTORY_WEEKS))
            ->where('created_at', '<', $today)
            ->groupBy('org_stock_id', 'weeks_ago')
            ->selectRaw('org_stock_id, floor((?::date - 1 - created_at::date) / 7)::int as weeks_ago, sum(quantity_dispatched)::float as dispatched', [$today->toDateString()])
            ->get();

        $histories = [];
        foreach ($rows->groupBy('org_stock_id') as $orgStockId => $weeks) {
            $oldest = (int) $weeks->max('weeks_ago');
            if ($oldest + 1 < self::MINIMUM_HISTORY_WEEKS) {
                continue;
            }
            $history = array_fill(0, $oldest + 1, 0.0);
            foreach ($weeks as $week) {
                $history[$oldest - (int) $week->weeks_ago] = (float) $week->dispatched;
            }
            $histories[$orgStockId] = $history;
        }

        return $histories;
    }

    /**
     * Days until the expected weekly demand adds up to what is available, spreading each week evenly.
     *
     * @param  list<array{0: float, 1: float}>  $weeks
     */
    private function daysOfCover(float $available, array $weeks): ?float
    {
        if ($available <= 0) {
            return 0.0;
        }

        $used = 0.0;
        foreach ($weeks as $week => [$expected]) {
            if ($expected > 0 && $used + $expected >= $available) {
                return round($week * 7 + 7 * ($available - $used) / $expected, 1);
            }
            $used += $expected;
        }

        return null;
    }

    /**
     * Once a week, what this forecast and the live one expect for the next 42 days, to be checked
     * against the dispatches when they are in.
     *
     * @param  list<array{from: string, timesfm: float, live: float|null}>  $record
     * @param  list<array{0: float, 1: float}>  $weeks
     *
     * @return list<array{from: string, timesfm: float, live: float|null}>
     */
    private function record(array $record, array $weeks, ?string $liveDailyUsage, Carbon $today): array
    {
        $last = end($record);
        if ($last && Carbon::parse($last['from'])->diffInDays($today) < 7) {
            return $record;
        }

        $record[] = [
            'from'    => $today->toDateString(),
            'timesfm' => round(array_sum(array_column(array_slice($weeks, 0, 6), 0)), 3),
            'live'    => $liveDailyUsage === null ? null : round((float) $liveDailyUsage * self::COMPARISON_DAYS, 3),
        ];

        return array_slice($record, -self::RECORDS_KEPT);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function store(array $rows): void
    {
        foreach (array_chunk($rows, 1000, true) as $chunk) {
            $values   = implode(', ', array_fill(0, count($chunk), '(?::int, ?::jsonb)'));
            $bindings = [];
            foreach ($chunk as $orgStockId => $forecast) {
                array_push($bindings, $orgStockId, json_encode($forecast));
            }

            DB::update(
                "update org_stock_stats set demand_forecast = v.forecast, demand_forecast_hydrated_at = now() from (values $values) as v(org_stock_id, forecast) where org_stock_stats.org_stock_id = v.org_stock_id",
                $bindings
            );
        }
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $count = $this->handle();
        $command->info("Forecast the demand of $count SKOs");

        return $count > 0 || !config('services.timesfm.url') ? 0 : 1;
    }
}
