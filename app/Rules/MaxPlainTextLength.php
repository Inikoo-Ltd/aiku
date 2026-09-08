<?php

/*
 * Author Louis Perez
 * Created on 26-08-2026-17h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Caps a rich text field by the letters the user typed, so the markup the editor wraps
 * around them does not eat into the allowance.
 */
class MaxPlainTextLength implements ValidationRule
{
    public function __construct(private int $max)
    {
    }

    public function validate($attribute, $value, $fail): void
    {
        if (!is_string($value)) {
            return;
        }

        $plainText = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5));

        if (mb_strlen($plainText) > $this->max) {
            $fail('The :attribute must not be greater than :max characters.')->translate([
                'max' => $this->max,
            ]);
        }
    }
}
