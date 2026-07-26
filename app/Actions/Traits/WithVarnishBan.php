<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Oct 2025 15:04:29 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Sentry;

trait WithVarnishBan
{
    /**
     * Sends many BANs in one concurrent round instead of sequentially, so a slow or
     * down varnish costs one timeout for the whole batch rather than one per ban.
     *
     * @param array<int, array<string, mixed>> $banExpressions header sets, one per ban
     */
    protected function sendVarnishBansHttpPool(array $banExpressions): void
    {
        if (!config('iris.cache.varnish') || !$banExpressions) {
            return;
        }

        $hosts = array_filter(config('iris.cache.varnish_hosts'));
        if (!$hosts) {
            return;
        }

        $responses = Http::pool(function ($pool) use ($hosts, $banExpressions) {
            $requests = [];
            foreach ($hosts as $varnishHost) {
                foreach ($banExpressions as $banExpression) {
                    $requests[] = $pool->timeout(3)->withHeaders($banExpression)->send('BAN', $varnishHost);
                }
            }

            return $requests;
        });

        foreach ($responses as $response) {
            if ($response instanceof \Throwable) {
                Sentry::captureException($response);
            }
        }
    }

    protected function sendVarnishBanHttp(array $banExpression, ?Command $command = null): void
    {
        if (!config('iris.cache.varnish')) {
            $command?->info('Varnish disabled, skipping BAN');

            return;
        }

        foreach (config('iris.cache.varnish_hosts') as $varnishHost) {
            if (!$varnishHost) {
                continue;
            }
            $command?->info('Sending Varnish BAN to '.$varnishHost);
            try {
                $response = Http::timeout(3)
                    ->withHeaders($banExpression)
                    ->send('BAN', $varnishHost);

                if ($command) {
                    $command->line('BAN sent: '.json_encode($banExpression));
                    $command->line('Varnish replied: '.$response->status().' '.$response->body());
                }
            } catch (\Throwable $e) {
                $command?->error('Failed to send Varnish BAN: '.$e->getMessage());
                Sentry::captureException($e);
            }
        }
    }
}
