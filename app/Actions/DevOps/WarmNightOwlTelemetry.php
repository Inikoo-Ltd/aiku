<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Oct 2026 12:00:00 Coordinated Universal Time
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps;

use App\Actions\DevOps\UI\GetNightOwlTelemetry;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class WarmNightOwlTelemetry
{
    use AsAction;

    public string $commandSignature = 'devops:warm_telemetry';
    public string $commandDescription = 'Pre-build the devops dashboard telemetry for every range';

    public function handle(): void
    {
        Nightwatch::dontSample();

        $telemetry = app(GetNightOwlTelemetry::class);
        foreach (array_keys(GetNightOwlTelemetry::RANGES) as $range) {
            $key = GetNightOwlTelemetry::cacheKey($range);
            Cache::putMany([
                $key                                           => $telemetry->build($range),
                Repository::FLEXIBLE_CREATED_KEY_PREFIX.$key => now()->getTimestamp(),
            ], GetNightOwlTelemetry::CACHE_TTL[1]);
        }
    }
}
