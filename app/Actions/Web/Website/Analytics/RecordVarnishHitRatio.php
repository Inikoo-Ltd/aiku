<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 10 May 2026 22:00:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Analytics;

use Arr;
use Cache;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry\Unit;

class RecordVarnishHitRatio
{
    use AsAction;

    public function handle(?Command $command = null): void
    {
        $output = shell_exec('sudo /usr/bin/varnishstat -f "MAIN.cache_hit" -f "MAIN.cache_miss"  -j');
        if ($output) {
            $data = json_decode($output, true);

            $hits   = $data['counters']['MAIN.cache_hit']['value'] ?? null;
            $misses = $data['counters']['MAIN.cache_miss']['value'] ?? null;

            $command?->line("Hits $hits ; Misses: $misses");

            $server              = config('app.server_name');
            $key                 = 'metric:varnish-hit-ratio:'.$server;
            $previousHitRateData = Cache::get($key);

            if ($previousHitRateData) {
                $previousHitRateData = json_decode($previousHitRateData, true);
                $previousHits        = Arr::get($previousHitRateData, 'hits');
                $previousMisses      = Arr::get($previousHitRateData, 'misses');
                $previousTimestamp   = Arr::get($previousHitRateData, 'timestamp');

                if ($previousHits > $hits) {
                    return;
                }

                if ((Carbon::now()->timestamp - $previousTimestamp) < 30) {
                    return;
                }


                $deltaHits   = $hits - $previousHits;
                $deltaMisses = $misses - $previousMisses;
                $totalDelta  = $deltaHits + $deltaMisses;

                $hitRate = $totalDelta > 0 ? ($deltaHits / $totalDelta) : 0;

                $command?->line("Hit rate: $hitRate");

                \Sentry\traceMetrics()->gauge(
                    'varnish.hit_ratio',
                    $hitRate,
                    [
                        'server' => config('app.server_name')
                    ],
                    Unit::ratio()
                );

                \Sentry\traceMetrics()->gauge(
                    'varnish.hits',
                    $deltaHits,
                    [
                        'server' => config('app.server_name')
                    ]
                );
                \Sentry\traceMetrics()->gauge(
                    'varnish.requests',
                    $totalDelta,
                    [
                        'server' => config('app.server_name')
                    ]
                );
                \Sentry\traceMetrics()->gauge(
                    'varnish.misses',
                    $deltaMisses,
                    [
                        'server' => config('app.server_name')
                    ]
                );
            }


            $hitsData = [
                'timestamp' => Carbon::now()->timestamp,
                'hits'      => $hits,
                'misses'    => $misses
            ];

            Cache::set($key, json_encode($hitsData), 7200);
        } else {
            echo "Failed to retrieve varnish metrics.";
        }
    }

    public function getCommandSignature(): string
    {
        return 'metrics:varnish_hit_ratio';
    }

    public function asCommand(Command $command): int
    {
        $this->handle($command);

        return 0;
    }
}
