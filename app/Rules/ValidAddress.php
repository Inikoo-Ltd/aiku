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
use Illuminate\Support\Facades\DB;

class ValidAddress implements ValidationRule
{
    /**
     * An address with only a country passes by default, because imports and prospects legitimately carry
     * one. Registration and anything else that must end up on paperwork asks for the street as well: an
     * all-empty address hashes to the Aurora "0" placeholder and prints as zeros on the invoice (HELP-3102).
     */
    public function __construct(private bool $requireStreet = false)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        if (is_object($value)) {
            $value = $value->toArray();
        }

        $query = DB::table('countries');
        if ($query->where("id", Arr::get($value, 'country_id'))->count() <= 0) {

            $fail(__('Invalid address'));
        }

        if ($this->requireStreet && in_array(trim((string)Arr::get($value, 'address_line_1')), ['', '0'], true)) {
            $fail(__('The address is required'));
        }
    }
}
