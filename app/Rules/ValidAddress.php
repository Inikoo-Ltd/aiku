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
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        if (is_object($value)) {
            $value = $value->toArray();
        }

        $query = DB::table('countries');
        if ($query->where('id', Arr::get($value, 'country_id'))->count() <= 0) {

            $fail(__('Invalid address'));
        }
    }
}
