<?php

namespace App\Actions\Helpers\Dashboard;

use App\Enums\DateIntervals\DateIntervalEnum;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;
use Illuminate\Support\Number;

class CalculateTimeSeriesStats
{
    use AsObject;

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

        $selects = [$foreignKey];
        $bindings = [];
        $intervals = DateIntervalEnum::cases();
        $now = now();

        if ($from_date && $to_date) {
            $start = Carbon::parse($from_date);
            $end = Carbon::parse($to_date);
            foreach ($metricsMapping as $outputKey => $column) {
                $selects[] = "SUM(CASE WHEN \"from\" >= ? AND \"from\" <= ? THEN $column ELSE 0 END) as {$outputKey}_ctm";
                $bindings[] = $start;
                $bindings[] = $end;
            }
            $startLy = $start->copy()->subYear();
            $endLy = $end->copy()->subYear();

            foreach ($metricsMapping as $outputKey => $column) {
                $selects[] = "SUM(CASE WHEN \"from\" >= ? AND \"from\" <= ? THEN $column ELSE 0 END) as {$outputKey}_ctm_ly";
                $bindings[] = $startLy;
                $bindings[] = $endLy;
            }
        }

        foreach ($intervals as $interval) {
            if ($interval === DateIntervalEnum::CUSTOM) {
                continue;
            }

            $range = $this->getIntervalRange($interval, $now);
            if (!$range) {
                continue;
            }

            [$start, $end] = $range;

            // Generate Selects for Current Period
            foreach ($metricsMapping as $outputKey => $column) {
                $selects[] = "SUM(CASE WHEN \"from\" >= ? AND \"from\" <= ? THEN $column ELSE 0 END) as {$outputKey}_{$interval->value}";
                $bindings[] = $start;
                $bindings[] = $end;
            }

            // Generate Selects for Last Year Period
            $startLy = $start->copy()->subYear();
            $endLy = $end->copy()->subYear();

            foreach ($metricsMapping as $outputKey => $column) {
                $selects[] = "SUM(CASE WHEN \"from\" >= ? AND \"from\" <= ? THEN $column ELSE 0 END) as {$outputKey}_{$interval->value}_ly";
                $bindings[] = $startLy;
                $bindings[] = $endLy;
            }
        }

        $query = DB::table($tableName)
            ->selectRaw(implode(', ', $selects), $bindings)
            ->whereIn($foreignKey, $timeSeriesIds);

        // Apply additional where conditions
        foreach ($additionalWhere as $column => $value) {
            $query->where($column, $value);
        }

        $results = $query->groupBy($foreignKey)->get();

        // Key results by foreign key (e.g., platform_time_series_id)
        return $results->keyBy($foreignKey)->map(fn ($item) => (array) $item)->toArray();
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

                // Format for currency if the key contains 'sales' or 'revenue'
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
}
