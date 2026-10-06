<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Oct 2026 21:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Api\Retina\Client;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * API users know the country of their buyer as an ISO code, never as our internal country id,
 * so address.country_code takes the two-letter (GB) or three-letter (GBR) code. country_id is
 * still accepted because existing integrations send it. Address fields we do not store are
 * dropped: saved as sent, an unknown key such as "city" failed the insert with a server error.
 */
trait WithApiClientAddress
{
    private const array API_ADDRESS_FIELDS = [
        'address_line_1',
        'address_line_2',
        'sorting_code',
        'postal_code',
        'locality',
        'dependent_locality',
        'administrative_area',
        'country_code',
        'country_id',
    ];

    private bool $apiAddressSent = false;

    private bool $apiAddressHasCountryId = false;

    private ?string $apiAddressUnknownCountryCode = null;

    public function prepareForValidation(): void
    {
        $address = $this->get('address');

        if (! is_array($address)) {
            return;
        }

        $this->apiAddressSent = true;
        $address              = Arr::only($address, self::API_ADDRESS_FIELDS);
        $countryCode          = strtoupper(trim((string) Arr::get($address, 'country_code', '')));

        if ($countryCode !== '') {
            $country = DB::table('countries')
                ->where(strlen($countryCode) === 3 ? 'iso3' : 'code', $countryCode)
                ->first(['id', 'code']);

            if ($country) {
                $address['country_id']   = $country->id;
                $address['country_code'] = $country->code;
            } else {
                $this->apiAddressUnknownCountryCode = $countryCode;
            }
        }

        $this->apiAddressHasCountryId = filled(Arr::get($address, 'country_id'));

        $this->set('address', $address);
    }

    public function apiAddressRules(): array
    {
        if (! $this->apiAddressSent) {
            return [];
        }

        return [
            'address.country_code' => [
                Rule::requiredIf(! $this->apiAddressHasCountryId && $this->apiAddressUnknownCountryCode === null),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->apiAddressUnknownCountryCode !== null) {
                        $fail(__('Unknown country code ":code". Send the ISO code of the country, two letters (GB) or three letters (GBR).', ['code' => $this->apiAddressUnknownCountryCode]));
                    }
                },
            ],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'address.required'              => __('The client\'s delivery address is required: send an "address" object with address_line_1, locality, postal_code and country_code.'),
            'address.country_code.required' => __('The country is required. Send address.country_code with the ISO code of the country, two letters (GB) or three letters (GBR).'),
        ];
    }
}
