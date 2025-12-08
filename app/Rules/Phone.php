<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jul 2024 12:39:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Phone implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $validate = preg_match('%^(?:(?:\(?(?:00|\+)([1-4]\d\d|[1-9]\d?)\)?)?[\-. /]?)?((?:\(?\d+\)?[\-. /]?)*)(?:[\-. /]?(?:#|ext\.?|extension|x)[\-. /]?(\d+))?$%i', $value) && strlen($value) >= 10;

        if (! $validate) {
            $fail(__('The phone :attribute is invalid', ['attribute' => $attribute]));
        }
    }
}
