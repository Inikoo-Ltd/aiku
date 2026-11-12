<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierAgreement;

use App\Actions\Web\Website\BlockedCountries\CheckIfCountryRegionsIsBlocked;
use App\Models\SupplyChain\SupplierAgreementAcceptance;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The city the agreement was accepted from, looked up from its IP after the supplier has its answer.
 */
class LocateSupplierAgreementAcceptance
{
    use AsAction;

    public function handle(SupplierAgreementAcceptance $acceptance): void
    {
        if (!$acceptance->ip || !$acceptance->country_code || $acceptance->ip_geolocation_id) {
            return;
        }

        $geoData = CheckIfCountryRegionsIsBlocked::make()->getRequestGeoData($acceptance->country_code, $acceptance->ip);
        if ($geoData) {
            $acceptance->update(['ip_geolocation_id' => $geoData['id']]);
        }
    }
}
