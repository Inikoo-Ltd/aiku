<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Jul 2026 15:46:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\BlockedCountries;

use App\Actions\Web\Website\Cloudflare\BlockCountriesInCloudflare;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncWebsiteBlockedCountries
{
    use AsAction;

    public function handle(Website $website, array $bannedCountries): void
    {
        $currentBlockedCountryRegions = $website->blocked_country_regions;


        $currentCountryWithRegionsData = array_filter($currentBlockedCountryRegions, fn ($item) => !empty($item['postcode']));
        $currentCountryOnly            = array_filter($currentBlockedCountryRegions, fn ($item) => empty($item['postcode']));

        $newCountryWithRegionsData = array_filter($bannedCountries, fn ($item) => !empty($item['postcode']));
        $newCountryWithCountryOnly = array_filter($bannedCountries, fn ($item) => empty($item['postcode']));


        foreach (array_diff(array_keys($currentCountryOnly), array_keys($newCountryWithCountryOnly)) as $countryToRemove) {
            UpdateWebsiteBlockedCountriesRegions::run(
                $website,
                [
                    'country' => $countryToRemove
                ]
            );
        }

        if (app()->isProduction()) {
            BlockCountriesInCloudflare::run($website, array_keys($newCountryWithCountryOnly));
        } else {
            foreach (array_keys($newCountryWithCountryOnly) as $countryCode) {
                UpdateWebsiteBlockedCountriesRegions::run(
                    $website,
                    [
                        'country' => $countryCode
                    ],
                    true
                );
            }
        }


        $currentCountryWithRegions = array_keys($currentCountryWithRegionsData);
        $newCountryWithRegions     = array_keys($newCountryWithRegionsData);

        foreach (array_diff($currentCountryWithRegions, $newCountryWithRegions) as $countryToRemove) {
            if (in_array($countryToRemove, array_keys($newCountryWithCountryOnly))) {
                continue;
            }

            UpdateWebsiteBlockedCountriesRegions::run($website, [
                'country' => $countryToRemove,
            ]);
        }

        foreach ($newCountryWithRegionsData as $countryCode => $countryWithRegions) {
            UpdateWebsiteBlockedCountriesRegions::run($website, [
                'country' => $countryCode,
                'postcode' => $countryWithRegions['postcode'],
            ]);
        }
    }

}
