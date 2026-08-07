<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAttributionStartedAt
{
    use AsAction;

    /**
     * The moment the first touch was ever recorded. Everything before it is unattributable by
     * definition, so comparing a month of trade against it reads as "marketing achieved 0%" when the
     * real statement is "we were not recording yet".
     *
     * Cached: it only moves once, the first time anything is captured.
     */
    public function handle(): ?Carbon
    {
        $timestamp = Cache::remember('marketing:attribution_started_at', now()->addHour(), function () {
            return DB::table('model_has_traffic_sources')->min('first_touch_at');
        });

        return $timestamp ? Carbon::parse($timestamp) : null;
    }
}
