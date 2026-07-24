<?php

/*
 * Author: ekayudinata <dev@aw-advantage.com>
 * Created: Wed, 22 Jul 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\PreferredShipping;

use App\Models\Catalogue\PreferredShipping;
use App\Models\CRM\Customer;

trait WithPreferredShipperResolver
{
    public function findShipperIdForAddress(int $shopId, ?int $countryId, ?string $postalCode, ?int $customerId = null): ?int
    {
        if (!$countryId) {
            return null;
        }

        $postalCode = strtoupper($postalCode ?? '');

        $rows = PreferredShipping::query()
            ->where('shop_id', $shopId)
            ->get();

        if ($important = $rows->firstWhere('important', true)) {
            return $important->shipper_id;
        }

        if ($customerId && $customerShipperId = Customer::find($customerId)?->shipper_id) {
            return $customerShipperId;
        }

        return $rows->first(
            fn (PreferredShipping $preferredShipping) => (!$preferredShipping->country_id || $preferredShipping->country_id === $countryId)
                && (!$preferredShipping->postcode || str_starts_with($postalCode, strtoupper($preferredShipping->postcode)))
        )?->shipper_id;
    }
}
