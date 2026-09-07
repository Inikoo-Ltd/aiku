<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 12:10:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\Retina\UI;

use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Registration forms open on the visitor's country as reported by Cloudflare when the shop
 * serves that country, otherwise on the shop's own country.
 *
 * @param array<int, array{code: string}> $countriesAddressData
 */
class GetRetinaRegistrationDefaultCountry
{
    use AsAction;

    public function handle(Shop $shop, array $countriesAddressData, ActionRequest $request): ?int
    {
        $visitorCountryCode = strtoupper((string) $request->header('CF-IPCountry'));

        foreach ($countriesAddressData as $countryId => $countryData) {
            if ($countryData['code'] === $visitorCountryCode) {
                return (int) $countryId;
            }
        }

        return isset($countriesAddressData[$shop->country_id]) ? $shop->country_id : null;
    }
}
