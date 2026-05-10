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

class RecordVarnishHitRate
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
            $key                 = 'metric:varnish-hit-rate:'.$server;
            $previousHitRateData = Cache::get($key);

            if ($previousHitRateData) {
                $previousHitRateData = json_decode($previousHitRateData, true);
                $previousHits        = Arr::get($previousHitRateData, 'hits');
                $previousMisses      = Arr::get($previousHitRateData, 'misses');
                $previousTimestamp   = Arr::get($previousHitRateData, 'timestamp');

                if ($previousHits > $hits) {
                    return;
                }

                if ((Carbon::now()->timestamp - $previousTimestamp) < 120) {
                    return;
                }


                $deltaHits   = $hits - $previousHits;
                $deltaMisses = $misses - $previousMisses;
                $totalDelta  = $deltaHits + $deltaMisses;

                $hitRate = $totalDelta > 0 ? ($deltaHits / $totalDelta) * 100 : 0;

                $command?->line("Hit rate: $hitRate");

                \Sentry\traceMetrics()->gauge(
                    'varnish.hit_rate',
                    $hitRate,
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
        return 'metrics:varnish_hit_rate';
    }

    public function asCommand(Command $command): int
    {
        $this->handle($command);

        return 0;
    }
}
