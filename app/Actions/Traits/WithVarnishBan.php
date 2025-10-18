<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Oct 2025 15:04:29 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

trait WithVarnishBan
{
    protected function sendVarnishBanHttp(array $banExpression, Command $command = null): array
    {
        try {
            $response = Http::timeout(3)
                ->withHeaders($banExpression)
                ->send('BAN', config('iris.cache.varnish_host'));

            if ($command) {
                $command->line('BAN sent: '.json_encode($banExpression));
                $command->line('Varnish replied: '.$response->status().' '.$response->body());
            }

            return $response['body'] ?? ['status' => 'success'];
        } catch (\Throwable $e) {
            $command?->error('Failed to send Varnish BAN: '.$e->getMessage());

            return ['status' => 'error', 'error' => $e->getMessage() ?? ''];
        }
    }
}
