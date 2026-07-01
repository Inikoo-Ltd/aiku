<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Jul 2026 12:53:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware\Concerns;

use App\Models\Web\Website;
use Illuminate\Support\Arr;

trait DetectsWebsite
{
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
