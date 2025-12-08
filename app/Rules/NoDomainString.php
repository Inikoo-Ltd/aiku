<?php

/*
 * author Arya Permana - Kirin
 * created on 12-03-2025-16h-10m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoDomainString implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $blockedTLDs = [
            'com',
            'net',
            'org',
            'gov',
            'edu',
            'co',
            'id',
            'uk',
            'us',
            'info',
            'biz',
            'xyz',
            'online',
            'site',
            'tech',
            'store',
            'io',
            'dev',
            'app',
            'ai',
            'me',
        ];

        if (preg_match('/\b(?:http:\/\/|https:\/\/|www\.)|\b[a-z0-9-]+\.('.implode('|', $blockedTLDs).')\b/i', $value)) {
            $fail('The :attribute must not be a domain or contain a URL-like structure.');
        }
    }
}
