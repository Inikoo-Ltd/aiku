<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Jul 2026 12:53:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware\Concerns;

use App\Models\Web\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

trait DetectsWebsite
{
    public const BLOCKED_COUNTRY_KEYS = ['has_blocked_country_regions', 'blocked_countries', 'blocked_country_regions'];

    public function applyWebsiteData(Request $request, array $websiteData): void
    {
        $request->attributes->add(Arr::only($websiteData, self::BLOCKED_COUNTRY_KEYS));
        $request->merge(Arr::except($websiteData, self::BLOCKED_COUNTRY_KEYS));
    }

    public function getWebsiteBaseData(Website $website): array
    {
        $websiteData = [
            'domain'  => $website->domain,
            'website' => $website,
        ];

        if (! empty($website->blocked_country_regions)) {
            $countries = [];
            foreach ($website->blocked_country_regions as $countryCode => $regions) {
                if (Arr::get($regions, 'cities') || Arr::get($regions, 'postcode')) {
                    $countries[] = $countryCode;
                }
            }

            $websiteData['has_blocked_country_regions'] = ! empty($countries);
            $websiteData['blocked_countries'] = $countries;
            $websiteData['blocked_country_regions'] = $website->blocked_country_regions;
        }

        return $websiteData;
    }
}
