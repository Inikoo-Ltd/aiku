<?php

namespace App\Actions\Helpers\Dashboard;

use App\Enums\DateIntervals\DateIntervalEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class CalculateTimeSeriesStats
{
    use AsObject;

    public function handle(Collection $dailyRecords, array $metricsMapping): array
    {
        $stats = [];
        $intervals = DateIntervalEnum::cases();
        $now = now();

        // Helper to sum metrics for a given date range
        $sumMetrics = function ($start, $end) use ($dailyRecords, $metricsMapping) {
            // Filter records that fall within the range [start, end]
            // We assume $record->from is a Carbon instance (casted in model)
            // or a string 'Y-m-d' which compares correctly.
            // Using string comparison is safer if 'from' is just a date string in DB but model casts it to Carbon.

            $filtered = $dailyRecords->filter(function ($record) use ($start, $end) {
                if ($record->from instanceof Carbon) {
                    return $record->from->betweenIncluded($start, $end);
                }
                // Fallback for string dates
                $date = Carbon::parse($record->from);
                return $date->betweenIncluded($start, $end);
            });

            $result = [];
            foreach ($metricsMapping as $outputKey => $column) {
                $result[$outputKey] = $filtered->sum($column);
            }
            return $result;
        };

        foreach ($intervals as $interval) {
            if ($interval === DateIntervalEnum::CUSTOM) {
                continue;
            }

            // 1. Calculate Current Period Range
            $range = $this->getIntervalRange($interval, $now);

            if ($range) {
                [$start, $end] = $range;

                // Calculate Stats for Current Period
                $sums = $sumMetrics($start, $end);
                foreach ($sums as $key => $value) {
                    $stats["{$key}_{$interval->value}"] = $value;
                }

                // 2. Calculate Last Year Period Range (_ly)
                // Shift dates back by 1 year
                $startLy = $start->copy()->subYear();
                $endLy = $end->copy()->subYear();

                $sumsLy = $sumMetrics($startLy, $endLy);
                foreach ($sumsLy as $key => $value) {
                    $stats["{$key}_{$interval->value}_ly"] = $value;
                }
            }
        }

        return $stats;
    }

    protected function getIntervalRange(DateIntervalEnum $interval, Carbon $now): ?array
    {
        // Logic adapted from DashboardIntervalFilters and DateIntervalEnum
        return match ($interval->value) {
            '1y'    => [$now->copy()->subYear()->startOfDay(), $now->copy()->endOfDay()],
            '1q'    => [$now->copy()->subQuarter()->startOfDay(), $now->copy()->endOfDay()],
            '1m'    => [$now->copy()->subMonth()->startOfDay(), $now->copy()->endOfDay()],
            '1w'    => [$now->copy()->subWeek()->startOfDay(), $now->copy()->endOfDay()],
            '3d'    => [$now->copy()->subDays(3)->startOfDay(), $now->copy()->endOfDay()],
            '1d'    => [$now->copy()->subDay()->startOfDay(), $now->copy()->endOfDay()], // 1d in Enum is subDay() (Yesterday/Last 24h?) - Wait, 1d usually means "Last 1 Day". Enum says 1d.

            'ytd'   => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            'qtd'   => [$now->copy()->startOfQuarter(), $now->copy()->endOfDay()],
            'mtd'   => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'wtd'   => [$now->copy()->startOfWeek(), $now->copy()->endOfDay()],
            'tdy'   => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],

            'lm'    => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'lw'    => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'ld'    => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],

            'all'   => [Carbon::create(1970, 1, 1), $now->copy()->endOfDay()], // Sufficiently old date

            default => null
        };
    }
}
