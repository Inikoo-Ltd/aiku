<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Apr 2023 10:14:19 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Traits;

use App\Models\Helpers\Country;
use CommerceGuys\Addressing\Address as Adr;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use CommerceGuys\Addressing\Country\CountryRepository;
use CommerceGuys\Addressing\Formatter\DefaultFormatter;
use CommerceGuys\Addressing\ImmutableAddressInterface;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

trait IsAddress
{
    private function getAdr(): ImmutableAddressInterface|Adr
    {
        $address = new Adr();

        return $address
            ->withCountryCode($this->country_code)
            ->withAdministrativeArea($this->administrative_area??'')
            ->withDependentLocality($this->dependent_locality??'')
            ->withLocality($this->locality??'')
            ->withPostalCode($this->postal_code??'')
            ->withSortingCode($this->sorting_code??'')
            ->withAddressLine2($this->address_line_2??'')
            ->withAddressLine1($this->address_line_1??'');
    }

    public function getFormattedAddressAttribute(): string
    {
        $addressFormatRepository = new AddressFormatRepository();
        $countryRepository       = new CountryRepository();
        $subdivisionRepository   = new SubdivisionRepository();
        $formatter               = new DefaultFormatter($addressFormatRepository, $countryRepository, $subdivisionRepository, ['html' => false]);

        return $formatter->format($this->getAdr());
    }

    public function getHtml(): string
    {
        $addressFormatRepository = new AddressFormatRepository();
        $countryRepository       = new CountryRepository();
        $subdivisionRepository   = new SubdivisionRepository();
        $formatter               = new DefaultFormatter($addressFormatRepository, $countryRepository, $subdivisionRepository);

        return $formatter->format($this->getAdr());
    }

    public function getChecksum(): string
    {
        $json = json_encode(
            array_filter(
                array_map(
                    function ($value) {
                        return $value !== null ? strtolower($value) : $value;
                    },
                    $this->only(
                        'address_line_1',
                        'address_line_2',
                        'sorting_code',
                        'postal_code',
                        'dependent_locality',
                        'locality',
                        'administrative_area',
                        'country_id'
                    )
                )
            )
        );

        return md5($json);
    }

    public function getCountryName(): string
    {
        if ($country = (new Country())->firstWhere('id', $this->country_id)) {
            return $country->name;
        }

        return '';
    }

    public function getLocation(): array
    {
        return [
            $this->country_code,
            $this->getCountryName(),
            $this->locality ?? $this->administrative_area ?? $this->postal_code
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'owner_type', 'owner_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function getFields(): array
    {
        return [
            'address_line_1'      => $this->address_line_1,
            'address_line_2'      => $this->address_line_2,
            'sorting_code'        => $this->sorting_code,
            'postal_code'         => $this->postal_code,
            'dependent_locality'  => $this->dependent_locality,
            'locality'            => $this->locality,
            'administrative_area' => $this->administrative_area,
            'country_code'        => $this->country_code,
            'country_id'          => $this->country_id,
        ];
    }

}
