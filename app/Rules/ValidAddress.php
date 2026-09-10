<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 25 Jun 2023 11:15:48 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use CommerceGuys\Addressing\AddressFormat\AddressField;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use Illuminate\Support\Facades\DB;

class ValidAddress implements ValidationRule
{
    /**
     * An address with only a country passes by default, because imports and prospects legitimately carry
     * one. Registration and anything else that ends up on paperwork asks for a real address: an all-empty
     * address hashes to the Aurora "0" placeholder and prints as zeros on the invoice (HELP-3102).
     *
     * What "real" means is per country and is not ours to guess: commerceguys/addressing already carries
     * each country's format, so we ask it which fields that country requires. The UAE has no town, Hong
     * Kong has no postal code, and neither should be blocked from registering.
     */
    public function __construct(private bool $requireFullAddress = false)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        if (is_object($value)) {
            $value = $value->toArray();
        }

        $country = DB::table('countries')->where('id', Arr::get($value, 'country_id'))->first();

        if (!$country) {
            $fail(__('Invalid address'));

            return;
        }

        if (!$this->requireFullAddress) {
            return;
        }

        /** Our address columns, keyed by the library's field name */
        $columns = [
            AddressField::ADDRESS_LINE1       => ['address_line_1', __('The address is required')],
            AddressField::LOCALITY            => ['locality', __('The town is required')],
            AddressField::POSTAL_CODE         => ['postal_code', __('The postal code is required')],
            AddressField::ADMINISTRATIVE_AREA => ['administrative_area', __('The province is required')],
        ];

        foreach ((new AddressFormatRepository())->get($country->code)->getRequiredFields() as $field) {
            if (!isset($columns[$field])) {
                continue;
            }

            [$column, $message] = $columns[$field];

            if ($this->isBlank(Arr::get($value, $column))) {
                $fail($message);
            }
        }
    }

    private function isBlank(mixed $line): bool
    {
        return in_array(trim((string)$line), ['', '0'], true);
    }
}
