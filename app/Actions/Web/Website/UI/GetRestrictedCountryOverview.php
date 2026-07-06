<?php

/*
 * Author: stewicca <wiccaalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Website\UI;

use App\Models\Web\RestrictedCountryRegionLog;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsObject;

class GetRestrictedCountryOverview
{
    use AsObject;

    public function handle(Website $website): array
    {
        $blockedCountries = array_keys($website->blocked_country_regions);

        if (empty($blockedCountries)) {
            return [];
        }

        return RestrictedCountryRegionLog::query()
            ->join('ip_geolocations', 'ip_geolocations.id', '=', 'restricted_country_region_logs.ip_geolocation_id')
            ->whereIn('ip_geolocations.country', $blockedCountries)
            ->whereNotNull('ip_geolocations.latitude')
            ->whereNotNull('ip_geolocations.longitude')
            ->groupBy('ip_geolocations.id')
            ->selectRaw('
                ip_geolocations.id,
                MAX(ip_geolocations.latitude) as latitude,
                MAX(ip_geolocations.longitude) as longitude,
                MAX(ip_geolocations.city) as city,
                MAX(ip_geolocations.postcode) as postcode,
                MAX(ip_geolocations.country) as country,
                SUM(restricted_country_region_logs.number_requests) as number_requests,
                MAX(restricted_country_region_logs.last_request_at) as last_request_at,
                MAX(CAST(restricted_country_region_logs.was_blocked AS int)) as was_blocked
            ')
            ->get()
            ->map(fn ($point) => [
                'latitude'        => (float) $point->latitude,
                'longitude'       => (float) $point->longitude,
                'country'         => $point->country,
                'city'            => $point->city,
                'postcode'        => $point->postcode,
                'was_blocked'     => (bool) $point->was_blocked,
                'number_requests' => (int) $point->number_requests,
                'last_request_at' => $point->last_request_at,
            ])
            ->all();
    }
}
