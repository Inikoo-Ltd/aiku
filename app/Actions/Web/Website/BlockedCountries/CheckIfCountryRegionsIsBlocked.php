<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Jun 2026 01:38:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\BlockedCountries;

use Exception;
use GeoIp2\WebService\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckIfCountryRegionsIsBlocked
{
    use AsAction;

    public function handle($request): bool
    {
        $isBlocked = false;
        if ($request->input('has_blocked_country_regions')) {
            $countryFromCloudFlare = $request->header('CF-IPCountry');
            if ($countryFromCloudFlare && in_array($countryFromCloudFlare, $request->input('blocked_countries'))) {
                $ip  = $request->ip();
                $key = "website-geo-blocked-ips:{$request->input('website')?->id}:$countryFromCloudFlare:$ip";

                $blockedData = Cache::remember($key, 28800, fn () => $this->isBlocked(
                    Arr::get($request->input('blocked_country_regions'), $countryFromCloudFlare),
                    $countryFromCloudFlare,
                    $ip
                ));

                $isBlocked = $blockedData[0];
                LogRestrictedCountryRegion::dispatch($blockedData, now());
            }
        }

        return $isBlocked;
    }


    public function isBlocked(array $blockedCountryRegions, string $countryCode, string $ip): array
    {
        $geoData = $this->getRequestGeoData($countryCode, $ip);
        if ($geoData == null) {
            return [false, null];
        }

        if (!empty($blockedCountryRegions['postcode']) && $geoData['postcode']) {
            if (preg_match($blockedCountryRegions['postcode'], $geoData['postcode'])) {
                return [true, $geoData['id']];
            }
        }

        if (!empty($blockedCountryRegions['city']) && $geoData['city']) {
            if (preg_match($blockedCountryRegions['city'], $geoData['city'])) {
                return [true, $geoData['id']];
            }
        }


        return [false, $geoData['id']];
    }

    public function getRequestGeoData(string $countryCode, string $ip): ?array
    {
        $existingGeoData = DB::table('ip_geolocations')
            ->select('id', 'city', 'postcode')
            ->where('country', $countryCode)
            ->where('ip', $ip)
            ->first();

        if ($existingGeoData) {
            return [
                'id'       => $existingGeoData->id,
                'city'     => $existingGeoData->city,
                'postcode' => $existingGeoData->postcode,
            ];
        }


        if (!config('services.max_mind.enabled')) {
            return null;
        }


        $client = new Client(
            config('services.max_mind.account_id'),
            config('services.max_mind.license_key'),
            ['en']
        );

        try {
            $record = $client->city($ip);
        } catch (Exception) {
            return null;
        }

        $geoData = [
            'city'      => $record->city->name,
            'postcode'  => $record->postal->code,
            'latitude'  => $record->location->latitude,
            'longitude' => $record->location->longitude,
        ];


        $id = DB::table('ip_geolocations')->insertGetId(
            [
                'ip'         => $ip,
                'country'    => $countryCode,
                'city'       => $geoData['city'],
                'postcode'   => $geoData['postcode'],
                'latitude'   => $geoData['latitude'],
                'longitude'  => $geoData['longitude'],
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );


        return [
            'id'       => $id,
            'city'     => $geoData['city'],
            'postcode' => $geoData['postcode'],
        ];
    }

}
