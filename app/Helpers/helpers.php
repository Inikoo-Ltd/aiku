<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Sep 2023 09:56:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Models\SysAdmin\Group;

if (!function_exists('group')) {
    function group(): ?Group
    {
        return Group::first();
    }
}


if (!function_exists('percentage')) {
    function percentage($quantity, $total, int $fixed = 1, ?string $errorMessage = null, $percentageSign = '%', $plusSing = false): string
    {
        $locale_info = localeconv();


        if ($total > 0) {
            if ($plusSing && $quantity > 0) {
                $sign = '+';
            } else {
                $sign = '';
            }

            $per = $sign.number_format(
                ($quantity / $total) * 100,
                $fixed,
                $locale_info['decimal_point'],
                $locale_info['thousands_sep']
            ).$percentageSign;
        } else {
            $per = $errorMessage === null ? percentage(0, 1) : $errorMessage;
        }

        return $per;
    }
}

if (!function_exists('findSmallestFactors')) {
    /**
     * Find the smallest factors (dividend and divisor) that can represent a number as a fraction.
     *
     * @param float $number The number to find factors for
     * @param float $epsilon The maximum allowed difference between the original number and the fraction
     * @return array An array containing [dividend, divisor]
     */
    function findSmallestFactors(float $number, float $epsilon = 0.00001): array
    {
        if ($number === 0.0) {
            return [0, 1];
        }

        $absNumber = abs($number);

        // For numbers less than 1, try to find the simplest fraction
        if ($absNumber < 1) {
            // Try denominators up to 100 to find the closest fraction
            $bestNumerator = 1;
            $bestDenominator = 1;
            $bestDiff = PHP_FLOAT_MAX;

            for ($denominator = 1; $denominator <= 100; $denominator++) {
                // Use round to get the closest numerator
                $numerator = 1;
                while ($numerator / $denominator <= $absNumber + $epsilon) {
                    $diff = abs(($numerator / $denominator) - $absNumber);
                    if ($diff < $bestDiff) {
                        $bestDiff = $diff;
                        $bestNumerator = $numerator;
                        $bestDenominator = $denominator;
                    }
                    $numerator++;
                }
            }

            if ($bestDiff < $epsilon) {
                return [$number < 0 ? -$bestNumerator : $bestNumerator, $bestDenominator];
            }
        } else {
            // For numbers greater than 1
            for ($i = 1; $i <= ceil($absNumber); $i++) {
                $possibleDivisor = $absNumber / $i;
                if (abs($possibleDivisor - round($possibleDivisor)) < $epsilon) {
                    $dividend = $i;
                    $divisor = (int)round($possibleDivisor);
                    return [$number < 0 ? -$dividend : $dividend, $divisor];
                }
            }
        }

        // Fallback for cases where no exact factors are found
        return [$number < 0 ? -1 : 1, 1];
    }
}
