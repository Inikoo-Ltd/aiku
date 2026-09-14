<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Helpers;

class IntrastatVatNumber
{
    public const string UNKNOWN = 'QV999999999999';

    private const array NATIONAL_FORMATS = [
        'AT' => '/^U\d{8}$/',
        'BE' => '/^[01]\d{9}$/',
        'BG' => '/^\d{9,10}$/',
        'CY' => '/^\d{8}[A-Z]$/',
        'CZ' => '/^\d{8,10}$/',
        'DE' => '/^\d{9}$/',
        'DK' => '/^\d{8}$/',
        'EE' => '/^\d{9}$/',
        'EL' => '/^\d{9}$/',
        'ES' => '/^[A-Z0-9]\d{7}[A-Z0-9]$/',
        'FI' => '/^\d{8}$/',
        'FR' => '/^[A-Z0-9]{2}\d{9}$/',
        'HR' => '/^\d{11}$/',
        'HU' => '/^\d{8}$/',
        'IE' => '/^\d{7}[A-W][A-IW]?$/',
        'IT' => '/^\d{11}$/',
        'LT' => '/^(\d{9}|\d{12})$/',
        'LU' => '/^\d{8}$/',
        'LV' => '/^\d{11}$/',
        'MT' => '/^\d{8}$/',
        'NL' => '/^\d{9}B\d{2}$/',
        'PL' => '/^\d{10}$/',
        'PT' => '/^\d{9}$/',
        'RO' => '/^\d{2,10}$/',
        'SE' => '/^\d{10}01$/',
        'SI' => '/^\d{8}$/',
        'SK' => '/^\d{10}$/',
    ];

    public static function intrastatPrefix(string $destinationCountryCode): string
    {
        return $destinationCountryCode === 'GR' ? 'EL' : $destinationCountryCode;
    }

    public static function normalise(?string $raw, string $destinationCountryCode): ?string
    {
        $vat = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string) $raw)));

        if ($vat === '') {
            return null;
        }

        if (str_starts_with($vat, 'GREL')) {
            $vat = substr($vat, 2);
        } elseif (str_starts_with($vat, 'GR')) {
            $vat = 'EL'.substr($vat, 2);
        }

        $destinationPrefix = self::intrastatPrefix($destinationCountryCode);

        if (!preg_match('/^[A-Z]{2}/', $vat)) {
            $vat = $destinationPrefix.$vat;
        }

        $prefix   = substr($vat, 0, 2);
        $national = substr($vat, 2);

        if ($prefix === 'BE' && preg_match('/^\d{9}$/', $national)) {
            $national = '0'.$national;
        }

        if ($prefix === 'SE' && preg_match('/^\d{10}$/', $national) && self::luhn($national)) {
            $national .= '01';
        }

        if ($prefix === $destinationPrefix) {
            return self::isValid($prefix, $national) ? $prefix.$national : null;
        }

        if (self::isValid($prefix, $national)) {
            return null;
        }

        return self::isValid($destinationPrefix, $national) ? $destinationPrefix.$national : null;
    }

    public static function isValid(string $prefix, string $national): bool
    {
        $format = self::NATIONAL_FORMATS[$prefix] ?? null;

        if (!$format || !preg_match($format, $national)) {
            return false;
        }

        return match ($prefix) {
            'BE'    => 97 - ((int) substr($national, 0, 8) % 97) === (int) substr($national, 8),
            'IT'    => self::luhn($national),
            'SE'    => self::luhn(substr($national, 0, 10)),
            'IE'    => self::irishCheck($national),
            default => true,
        };
    }

    private static function luhn(string $digits): bool
    {
        $sum    = 0;
        $length = strlen($digits);

        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $digits[$length - 1 - $i];

            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        return $sum % 10 === 0;
    }

    private static function irishCheck(string $national): bool
    {
        $sum = 0;

        for ($i = 0; $i < 7; $i++) {
            $sum += (int) $national[$i] * (8 - $i);
        }

        if (strlen($national) === 9) {
            $sum += 9 * (ord($national[8]) - 64);
        }

        $remainder = $sum % 23;
        $expected  = $remainder === 0 ? 'W' : chr(64 + $remainder);

        return $national[7] === $expected;
    }
}
