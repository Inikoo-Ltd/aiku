<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Oct 2026 04:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\Server;

use App\Events\BroadcastServerLiveMetrics;
use Illuminate\Support\Facades\Redis;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreServerLiveMetric
{
    use AsAction;

    public const int KEEP_READINGS = 120;

    public static function cacheKey(string $slug): string
    {
        return 'devops-server-live:'.$slug;
    }

    /** @return array<int, array{t: int, cpu_percent: float, iowait_percent: float|null, memory_percent: float, net_rx_mbps: float|null, net_tx_mbps: float|null}> */
    public static function recentReadings(string $slug): array
    {
        return array_map(fn (string $reading) => json_decode($reading, true), Redis::connection('devops')->lrange(self::cacheKey($slug), 0, -1));
    }

    public function handle(string $slug, array $reading): void
    {
        $reading = ['t' => now()->getTimestampMs(), ...array_map('floatval', $reading)];

        $key = self::cacheKey($slug);
        Redis::connection('devops')->pipeline(function ($pipe) use ($key, $reading) {
            $pipe->rpush($key, json_encode($reading));
            $pipe->ltrim($key, -self::KEEP_READINGS, -1);
            $pipe->expire($key, 600);
        });

        BroadcastServerLiveMetrics::dispatch($slug, $reading);
    }

    public function rules(): array
    {
        return [
            'cpu_percent'    => ['required', 'numeric', 'between:0,100'],
            'iowait_percent' => ['nullable', 'numeric', 'between:0,100'],
            'memory_percent' => ['required', 'numeric', 'between:0,100'],
            'net_rx_mbps'    => ['nullable', 'numeric', 'min:0'],
            'net_tx_mbps'    => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function asController(string $serverSlug, ActionRequest $request): array
    {
        abort_unless(preg_match('/^[a-z0-9-]{1,32}$/', $serverSlug), 422);

        $this->handle($serverSlug, $request->validated());

        return ['ok' => true];
    }
}
