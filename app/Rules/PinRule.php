<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Jul 2024 23:10:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Rules;

use App\Actions\HumanResources\Employee\SetEmployeePin;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class PinRule implements ValidationRule
{
    protected int $organisationId;

    public function __construct($organisationId)
    {
        $this->organisationId = $organisationId;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        [$letters, $emojis, $numbers] = SetEmployeePin::make()->pinCharacterSet();

        $letterCount = 0;
        $emojiCount = 0;
        $numberCount = 0;

        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($characters as $char) {
            if (in_array($char, $letters)) {
                $letterCount++;
            }
            if (in_array($char, $emojis)) {
                $emojiCount++;
            }
            if (in_array($char, $numbers)) {
                $numberCount++;
            }
        }

        if ($letterCount < 2 || $emojiCount < 2 || $numberCount < 2) {
            $fail(__('The pin must contain 2 letters, 2 numbers, and 2 emojis from the set.'));
        }

        $exists = DB::table('employees')
            ->where('organisation_id', $this->organisationId)
            ->where('pin', $value)
            ->exists();

        if ($exists) {
            $fail(__('The pin :attribute is invalid, try again', ['attribute' => $attribute]));
        }
    }
}
