<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 10 May 2026 22:00:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Analytics;

use Lorisleiva\Actions\Concerns\AsAction;
use Sentry\Unit;

class RecordVarnishMemoryUsage
{
    use AsAction;

    public function handle(): void
    {
        $output = shell_exec('sudo /usr/bin/varnishstat -f "SMA.s0.g_bytes" -j');
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
}
