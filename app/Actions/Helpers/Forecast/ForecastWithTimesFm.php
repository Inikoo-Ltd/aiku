<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Forecast;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

/**
 * Forecasts with Google's TimesFM, served by devops/timesfm/server.py on the forecast box. Each
 * series comes back as nine percentiles (10th to 90th) per step ahead. Null when the service is
 * not configured or does not answer, so callers keep their own method.
 */
class ForecastWithTimesFm
{
    use AsObject;

    private const int SERIES_PER_REQUEST = 5000;

    /**
     * @param  array<array-key, list<float>>  $series  history per key, oldest first, evenly spaced
     *
     * @return array{version: string, deciles: array<array-key, list<list<float>>>}|null
     */
    public function handle(array $series, int $horizon): ?array
    {
        $url = config('services.timesfm.url');

        if (!$url || !$series || $horizon < 1) {
            return null;
        }

        $version = null;
        $deciles = [];

        foreach (array_chunk($series, self::SERIES_PER_REQUEST, true) as $chunk) {
            try {
                $response = Http::withToken((string) config('services.timesfm.token'))
                    ->connectTimeout(10)
                    ->timeout(600)
                    ->retry(2, 2000, fn (Throwable $exception) => $exception instanceof ConnectionException)
                    ->post(rtrim($url, '/').'/forecast', [
                        'horizon' => $horizon,
                        'series'  => array_values(array_map(fn (array $values) => array_map('floatval', array_values($values)), $chunk)),
                    ])
                    ->throw();
            } catch (Throwable $exception) {
                Log::error('ForecastWithTimesFm: '.$exception->getMessage());

                return null;
            }

            $version = (string) $response->json('version');
            $deciles = $deciles + array_combine(array_keys($chunk), $response->json('deciles'));
        }

        return ['version' => $version, 'deciles' => $deciles];
    }

    /**
     * The expected value of each step: the average of its nine percentiles, each floored at zero.
     * The median would undershoot totals of uneven demand, where most days sell little and a few a lot.
     *
     * @param  list<list<float>>  $deciles
     *
     * @return list<float>
     */
    public static function expectedValues(array $deciles): array
    {
        return array_map(fn (array $step) => array_sum(array_map(fn (float|int $value) => max(0.0, (float) $value), $step)) / count($step), $deciles);
    }
}
