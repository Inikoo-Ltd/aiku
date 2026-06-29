<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Jun 2026 00:34:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\BlockedCountries;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class LogRestrictedCountryRegion
{
    use AsAction;


    public string $jobQueue = 'analytics';
    public int $jobTries = 1;

    public function handle(array $blockedData, Carbon $date): void
    {
        $isBlocked = $blockedData[0];
        $geoDataId = $blockedData[1];


        if ($geoDataId === null) {
            return;
        }

        DB::table('restricted_country_region_logs')->upsert(
            [
                'ip_geolocation_id' => $geoDataId,
                'was_blocked'       => $isBlocked,
                'last_request_at'   => $date,
                'number_requests'   => 1,
                'created_at'        => $date,
                'updated_at'        => $date,
            ],
            ['ip_geolocation_id', 'was_blocked'],
            [
                'last_request_at',
                'number_requests' => DB::raw('restricted_country_region_logs.number_requests + 1'),
                'updated_at',
            ]
        );
    }
}
