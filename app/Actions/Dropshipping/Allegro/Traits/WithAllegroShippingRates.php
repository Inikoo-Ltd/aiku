<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Traits;

use App\Models\Dropshipping\AllegroUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait WithAllegroShippingRates
{
    /**
     * Our own shipping price lists are named with this prefix, which is how we tell them
     * apart from the ones the seller already had on their account.
     */
    public const string ALLEGRO_SHIPPING_RATES_PREFIX = 'AW-EU-';

    public function allegroShippingRatesName(AllegroUser $allegroUser, string $countryCode): string
    {
        return self::ALLEGRO_SHIPPING_RATES_PREFIX . $allegroUser->customerSalesChannel->slug . '-' . $countryCode;
    }

    public function isAllegroShippingRatesOurs(?string $name): bool
    {
        return Str::startsWith((string) $name, self::ALLEGRO_SHIPPING_RATES_PREFIX);
    }

    /**
     * The id of the seller's shipping price list we should put our offers on.
     *
     * Only ever one of ours: taking whichever list the account happened to return first put
     * every offer we created on the seller's own pre-existing list, at their own prices, for
     * a parcel they do not send.
     */
    public function findOurAllegroShippingRatesId(AllegroUser $allegroUser): ?string
    {
        $shippingRates = collect(Arr::get($allegroUser->getShippingRates(), 'shippingRates', []));

        $ours = $shippingRates->first(fn ($rates) => $this->isAllegroShippingRatesOurs(Arr::get($rates, 'name')));

        return Arr::get($ours, 'id');
    }
}
