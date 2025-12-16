<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Dec 2025 10:15:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Models\Catalogue\Shop;
use App\Models\Ordering\ShippingCountry;
use Lorisleiva\Actions\Concerns\AsObject;

class GetShopShippingCountries
{
    use AsObject;

    /**
     * Get shipping countries for a shop formatted for the frontend component.
     *
     * @param Shop $shop
     * @return array
     */
    public function handle(Shop $shop): array
    {
        $shippingCountries = [];

        /** @var ShippingCountry $shippingCountry */
        foreach ($shop->shippingCountries()->with('country')->get() as $shippingCountry) {
            $country = $shippingCountry->country;

            $shippingCountries[] = [
                'id'                    => $shippingCountry->id,
                'country_id'            => $shippingCountry->country_id,
                'country_code'          => $country->code,
                'country_name'          => $country->name,
                'label'                 => $country->name . ' (' . $country->code . ')',
                'included_postal_codes' => $shippingCountry->territories['included_postal_codes'] ?? null,
                'excluded_postal_codes' => $shippingCountry->territories['excluded_postal_codes'] ?? null,
                'territories'           => $shippingCountry->territories
            ];
        }

        return $shippingCountries;
    }
}
