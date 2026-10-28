<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink;

use App\Actions\Web\Website\BlockedCountries\CheckIfCountryRegionsIsBlocked;
use App\Models\SupplyChain\SupplierShareLinkView;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Where a supplier page was opened from: the city of its IP, looked up once per IP and cached, after the page is
 * already on the supplier's screen.
 */
class LocateSupplierShareLinkView
{
    use AsAction;

    public function handle(SupplierShareLinkView $view): void
    {
        if (!$view->ip || !$view->country_code || $view->ip_geolocation_id) {
            return;
        }

        $geoData = CheckIfCountryRegionsIsBlocked::make()->getRequestGeoData($view->country_code, $view->ip);
        if ($geoData) {
            $view->update(['ip_geolocation_id' => $geoData['id']]);
        }
    }
}
