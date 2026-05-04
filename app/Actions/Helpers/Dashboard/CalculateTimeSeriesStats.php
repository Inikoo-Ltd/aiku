<?php

namespace App\Actions\Helpers\Dashboard;

use App\Enums\DateIntervals\DateIntervalEnum;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lorisleiva\Actions\Concerns\AsObject;
use Illuminate\Support\Number;

class CalculateTimeSeriesStats
{
    use AsObject;

    protected int $chunkSize = 100;

    public function handle(
        array $timeSeriesIds,
        array $metricsMapping,
        string $tableName,
        string $foreignKey,
        $from_date = null,
        $to_date = null,
        array $additionalWhere = []
    ): array {
        if (empty($timeSeriesIds)) {
            return [];
        }

        if (count($timeSeriesIds) > $this->chunkSize) {
            return $this->handleChunked($timeSeriesIds, $metricsMapping, $tableName, $foreignKey, $from_date, $to_date, $additionalWhere);
        }

        return $this->processTimeSeriesIds($timeSeriesIds, $metricsMapping, $tableName, $foreignKey, $from_date, $to_date, $additionalWhere);
    }

    protected function handleChunked(
        array $timeSeriesIds,
        array $metricsMapping,
        string $tableName,
        string $foreignKey,
        $from_date,
        $to_date,
        array $additionalWhere
    ): array {
        $chunks = array_chunk($timeSeriesIds, $this->chunkSize);
        $results = [];

        foreach ($chunks as $chunk) {
            $chunkResults = $this->processTimeSeriesIds($chunk, $metricsMapping, $tableName, $foreignKey, $from_date, $to_date, $additionalWhere);
            $results = array_merge($results, $chunkResults);

            unset($chunkResults);
        }

        return $results;
    }

    protected function processTimeSeriesIds(
        array $timeSeriesIds,
        array $metricsMapping,
        string $tableName,
        string $foreignKey,
        $from_date,
        $to_date,
        array $additionalWhere
    ): array {
        $selects = [$foreignKey];
        $bindings = [];
        $intervals = DateIntervalEnum::cases();
        $now = now();
        $cacheHash = $this->buildAggregateCacheHash(
            $timeSeriesIds,
            $metricsMapping,
            $tableName,
            $foreignKey,
            $from_date,
            $to_date,
            $additionalWhere
        );
        $cachedResults = $this->getCachedAggregates($cacheHash);
        if ($cachedResults !== null) {
            return $cachedResults;
        }

        if ($from_date && $to_date) {
            $start = Carbon::parse($from_date)->startOfDay();
            $end = Carbon::parse($to_date)->endOfDay();
            foreach ($metricsMapping as $outputKey => $column) {
                $selects[] = "SUM(CASE WHEN \"from\" >= ? AND \"from\" <= ? THEN $column ELSE 0 END) as {$outputKey}_ctm";
                $bindings[] = $start;
                $bindings[] = $end;
            }
            $startLy = $this->getSameDayPreviousYear($start);
            $endLy = $this->getSameDayPreviousYear($end);

            foreach ($metricsMapping as $outputKey => $column) {
                $selects[] = "SUM(CASE WHEN \"from\" >= ? AND \"from\" <= ? THEN $column ELSE 0 END) as {$outputKey}_ctm_ly";
                $bindings[] = $startLy;
                $bindings[] = $endLy;
            }
        }

        foreach ($intervals as $interval) {
            $range = $this->getIntervalRange($interval, $now);
            if (!$range) {
                continue;
            }

            [$start, $end] = $range;

            foreach ($metricsMapping as $outputKey => $column) {
                $selects[] = "SUM(CASE WHEN \"from\" >= ? AND \"from\" <= ? THEN $column ELSE 0 END) as {$outputKey}_{$interval->value}";
                $bindings[] = $start;
                $bindings[] = $end;
            }

            $startLy = $this->getSameDayPreviousYear($start);
            $endLy = $this->getSameDayPreviousYear($end);

            foreach ($metricsMapping as $outputKey => $column) {
                $selects[] = "SUM(CASE WHEN \"from\" >= ? AND \"from\" <= ? THEN $column ELSE 0 END) as {$outputKey}_{$interval->value}_ly";
                $bindings[] = $startLy;
                $bindings[] = $endLy;
            }
        }

        $query = DB::table($tableName)
            ->selectRaw(implode(', ', $selects), $bindings)
            ->whereIn($foreignKey, $timeSeriesIds);

        foreach ($additionalWhere as $column => $value) {
            $query->where($column, $value);
        }

        $results = $query->groupBy($foreignKey)->get();

        $mappedResults = $results->keyBy($foreignKey)->map(fn ($item) => (array) $item)->toArray();
        $this->storeCachedAggregates(
            $cacheHash,
            $timeSeriesIds,
            $metricsMapping,
            $tableName,
            $foreignKey,
            $from_date,
            $to_date,
            $additionalWhere,
            $mappedResults
        );

        return $mappedResults;
    }

    public function format(array $stats, array $metricsMapping, ?string $currencyCode = null): array
    {
        $formattedStats = [];

        $intervals = DateIntervalEnum::cases();

        foreach ($metricsMapping as $metricKey => $column) {
            $formattedStats[$metricKey] = [];
            $formattedStats[$metricKey . '_delta'] = [];

            foreach ($intervals as $interval) {
                $intervalValue = $interval->value;

                $currentValueKey = "{$column}_{$intervalValue}";
                $lastYearValueKey = "{$currentValueKey}_ly";

                $currentValue = (float)($stats[$currentValueKey] ?? 0);
                $lastYearValue = (float)($stats[$lastYearValueKey] ?? 0);

                if (str_contains($metricKey, 'sales') || str_contains($metricKey, 'revenue')) {
                    $formattedValue = $currencyCode ? Number::currency($currentValue, $currencyCode) : $currentValue;
                } else {
                    $formattedValue = number_format($currentValue);
                }

                $formattedStats[$metricKey][$intervalValue] = [
                    'raw_value'       => $currentValue,
                    'tooltip'         => '', // You can add logic for tooltips if needed
                    'formatted_value' => $formattedValue,
                ];

                $delta = $this->calculateDelta($currentValue, $lastYearValue);
                $formattedStats[$metricKey . '_delta'][$intervalValue] = $delta;
            }
        }

        return $formattedStats;
    }

    private function calculateDelta(float $current, float $previous): array
    {
        if ($previous == 0) {
            $percentageChange = $current > 0 ? 100 : 0;
        } else {
            $percentageChange = (($current - $previous) / $previous) * 100;
        }

        if ($percentageChange > 0) {
            $deltaIcon = ['icon' => 'fa-arrow-up', 'variant' => 'success'];
            $formattedValue = '+' . number_format($percentageChange, 1) . '%';
        } elseif ($percentageChange < 0) {
            $deltaIcon = ['icon' => 'fa-arrow-down', 'variant' => 'danger'];
            $formattedValue = number_format($percentageChange, 1) . '%';
        } else {
            $deltaIcon = ['icon' => 'fa-minus', 'variant' => 'secondary'];
            $formattedValue = '0.0%';
        }

        return [
            'raw_value'       => $percentageChange,
            'tooltip'         => (string)$previous,
            'formatted_value' => $formattedValue,
            'delta_icon'      => $deltaIcon,
        ];
    }

    protected function getSameDayPreviousYear(Carbon $date): Carbon
    {
        $previousYear = $date->year - 1;
        $isoWeek      = $date->isoWeek;
        $isoDayOfWeek = $date->dayOfWeekIso;

        $maxWeeks = Carbon::create($previousYear, 12, 28)->isoWeeksInYear();
        if ($isoWeek > $maxWeeks) {
            $isoWeek = $maxWeeks;
        }

        return Carbon::now()
            ->setISODate($previousYear, $isoWeek, $isoDayOfWeek)
            ->setTime($date->hour, $date->minute, $date->second);
    }

    protected function getIntervalRange(DateIntervalEnum $interval, Carbon $now): ?array
    {
        return match ($interval->value) {
            '1y'    => [$now->copy()->subYear()->startOfDay(), $now->copy()->endOfDay()],
            '1q'    => [$now->copy()->subQuarter()->startOfDay(), $now->copy()->endOfDay()],
            '1m'    => [$now->copy()->subMonth()->startOfDay(), $now->copy()->endOfDay()],
            '1w'    => [$now->copy()->subWeek()->startOfDay(), $now->copy()->endOfDay()],
            '3d'    => [$now->copy()->subDays(3)->startOfDay(), $now->copy()->endOfDay()],
            '1d'    => [$now->copy()->subDay()->startOfDay(), $now->copy()->endOfDay()],
            'ytd'   => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            'qtd'   => [$now->copy()->startOfQuarter(), $now->copy()->endOfDay()],
            'mtd'   => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'wtd'   => [$now->copy()->startOfWeek(), $now->copy()->endOfDay()],
            'tdy'   => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'lm'    => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'lw'    => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'ld'    => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'all'   => [Carbon::create(1970, 1, 1), $now->copy()->endOfDay()],
            default => null
        };
    }

    private function buildAggregateCacheHash(
        array $timeSeriesIds,
        array $metricsMapping,
        string $tableName,
        string $foreignKey,
        $fromDate,
        $toDate,
        array $additionalWhere
    ): string {
        sort($timeSeriesIds);
        ksort($metricsMapping);
        ksort($additionalWhere);

        return hash('sha256', json_encode([
            'table' => $tableName,
            'foreign_key' => $foreignKey,
            'ids' => $timeSeriesIds,
            'metrics' => $metricsMapping,
            'from_date' => $fromDate ? Carbon::parse((string) $fromDate)->toDateString() : 'all',
            'to_date' => $toDate ? Carbon::parse((string) $toDate)->toDateString() : 'all',
            'where' => $additionalWhere,
        ]));
    }

    private function getCachedAggregates(string $cacheHash): ?array
    {
        if (!Schema::hasTable('dashboard_time_series_aggregates')) {
            return null;
        }

        $cached = DB::table('dashboard_time_series_aggregates')
            ->where('cache_hash', $cacheHash)
            ->where('expires_at', '>', now())
            ->first();

        if (!$cached) {
            return null;
        }

        $payload = json_decode((string) $cached->payload, true);
        if (!is_array($payload)) {
            return null;
        }

        return $payload;
    }

    private function storeCachedAggregates(
        string $cacheHash,
        array $timeSeriesIds,
        array $metricsMapping,
        string $tableName,
        string $foreignKey,
        $fromDate,
        $toDate,
        array $additionalWhere,
        array $payload
    ): void {
        if (!Schema::hasTable('dashboard_time_series_aggregates')) {
            return;
        }

        DB::table('dashboard_time_series_aggregates')->updateOrInsert(
            ['cache_hash' => $cacheHash],
            [
                'table_name' => $tableName,
                'foreign_key' => $foreignKey,
                'time_series_ids_hash' => hash('sha256', json_encode(array_values($timeSeriesIds))),
                'metrics_hash' => hash('sha256', json_encode($metricsMapping)),
                'additional_where_hash' => hash('sha256', json_encode($additionalWhere)),
                'from_date' => $fromDate ? Carbon::parse((string) $fromDate)->toDateString() : null,
                'to_date' => $toDate ? Carbon::parse((string) $toDate)->toDateString() : null,
                'payload' => json_encode($payload),
                'expires_at' => now()->addMinutes(30),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
