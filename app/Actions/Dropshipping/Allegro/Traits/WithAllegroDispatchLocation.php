<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Traits;

trait WithAllegroDispatchLocation
{
    /**
     * Where the parcels actually leave from.
     *
     * Allegro takes the seller's own address as the dispatch location unless the offer says
     * otherwise, so an offer we create showed the seller's home address rather than the hub
     * the goods are picked and posted from. The seller never touches the stock, so this is
     * the same address for every seller.
     *
     * @return array{street: string, city: string, post_code: string, country_code: string}
     */
    public function allegroDispatchAddress(): array
    {
        return [
            'street'       => 'CTPark Trnava',
            'city'         => 'Zavar',
            'post_code'    => '919 26',
            'country_code' => 'SK',
        ];
    }

    /**
     * @return array{city: string, countryCode: string, postCode: string}
     */
    public function allegroOfferLocation(): array
    {
        $address = $this->allegroDispatchAddress();

        return [
            'city'        => $address['city'],
            'countryCode' => $address['country_code'],
            'postCode'    => $address['post_code'],
        ];
    }
}
