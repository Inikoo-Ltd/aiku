<?php

namespace App\Actions\Helpers\Dashboard;

use App\Enums\DateIntervals\DateIntervalEnum;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class CalculateTimeSeriesStats
{
    use AsObject;

    public function handle(array $timeSeriesIds, array $metricsMapping, $from_date = null, $to_date = null): array
    {
        if (empty($timeSeriesIds)) {
            return [];
        }

        $selects = ['offer_time_series_id'];
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

        $results = DB::table('offer_time_series_records')
            ->selectRaw(implode(', ', $selects), $bindings)
            ->whereIn('offer_time_series_id', $timeSeriesIds)
            ->groupBy('offer_time_series_id')
            ->get();

        // Key results by offer_time_series_id
        return $results->keyBy('offer_time_series_id')->map(fn ($item) => (array) $item)->toArray();
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
