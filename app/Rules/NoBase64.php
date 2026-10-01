<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 01 Oct 2026
 * Copyright (c) 2026
 */

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects code that carries base64 data, such as images pasted inline as data URIs
 * or scripts decoded at runtime with atob(), so web blocks stay small and readable.
 */
class NoBase64 implements ValidationRule
{
    public function validate($attribute, $value, $fail): void
    {
        if (!is_string($value)) {
            return;
        }

        if (preg_match('/base64|\batob\s*\(/i', $value)) {
            $fail(__('Scripts cannot contain base64 data. Upload images to the website and link to them instead.'));
        }
    }
}
