<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Helpers\Country\UI\IsEuropeanUnion;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\Concerns\AsObject;

class SetOrderDeliveryCountry
{
    use AsObject;

    public function handle(Order $order, ?Address $address): Order
    {
        $order->updateQuietly([
            'delivery_country_id' => $address?->country_id,
            'is_export'           => $address ? self::isExportDelivery($order->organisation->country->code, $address) : false,
        ]);

        return $order;
    }

    /**
     * Export = the parcel leaves the customs territory of the organisation: the UK for GB organisations
     * (Jersey, Guernsey and the Isle of Man count as exports), the EU for the rest, where the Canary Islands,
     * Ceuta and Melilla are outside the EU customs territory despite carrying the ES country code.
     */
    public static function isExportDelivery(string $organisationCountryCode, Address $address): bool
    {
        if ($organisationCountryCode == 'GB') {
            return $address->country_code != 'GB';
        }

        if ($address->country_code == 'ES' && preg_match('/^(35|38|51|52)/', $address->postal_code ?? '')) {
            return true;
        }

        return !IsEuropeanUnion::run($address->country_code);
    }
}
