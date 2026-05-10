<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 10 May 2026 22:00:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Analytics;

use Arr;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry\Unit;

class RecordVarnishHitRate
{
    use AsAction;

    public function handle(?Command $command=null): void
    {
        $output = shell_exec('sudo /usr/bin/varnishstat -f "MAIN.cache_hit" -f "MAIN.cache_miss"  -j');
        if ($output) {
            $data = json_decode($output, true);



            $gBytes    = $data['SMA.s0.g_bytes']['value'] ?? null;
            $megabytes = $gBytes === null
                ? null
                : round($gBytes / 1024 / 1024, 2);

            if ($megabytes && is_numeric($megabytes)) {
                \Sentry\traceMetrics()->gauge(
                    'varnish.used_memory',
                    $megabytes,
                    [
                        'server' => config('app.server_name')
                    ],
                    Unit::megabyte()
                );
            }
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
