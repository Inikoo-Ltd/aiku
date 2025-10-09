<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:18:34 British Summer Time, Bojnice, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Address;

use App\Http\Resources\Helpers\AddressResource;
use App\Models\Helpers\Address;
use CommerceGuys\Addressing\Address as Adr;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use CommerceGuys\Addressing\Country\CountryRepository;
use CommerceGuys\Addressing\Formatter\DefaultFormatter;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;
use Lorisleiva\Actions\Concerns\AsObject;

class GetFormattedAddress
{
    use AsObject;

    public function handle(Address|AddressResource $address): ?string
    {

        $addressFormatRepository = new AddressFormatRepository();
        $countryRepository       = new CountryRepository();
        $subdivisionRepository   = new SubdivisionRepository();
        $formatter               = new DefaultFormatter($addressFormatRepository, $countryRepository, $subdivisionRepository);

        $adr = new Adr();
        $adr = $adr
            ->withCountryCode($address->country_code ?? '')
            ->withAdministrativeArea($address->administrative_area ?? '')
            ->withDependentLocality($address->dependent_locality ?? '')
            ->withLocality($address->locality ?? '')
            ->withPostalCode($address->postal_code ?? '')
            ->withSortingCode($address->sorting_code ?? '')
            ->withAddressLine2($address->address_line_2 ?? '')
            ->withAddressLine1($address->address_line_1 ?? '');


        return $adr->getCountryCode() ? $formatter->format($adr) : null;

    }
}
