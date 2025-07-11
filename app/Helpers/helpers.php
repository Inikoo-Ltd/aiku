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
     * @param int $retryCount Internal parameter to prevent infinite recursion
     * @return array An array containing [dividend, divisor]
     */
    function findSmallestFactors(float $number, float $epsilon = 0.00001, int $retryCount = 0): array
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
        $result = [$number < 0 ? -1 : 1, 1];

        // If the result is [1,1] and the number is not 1 (or -1), try again with a larger epsilon
        if ($result == [1, 1] && abs($number) != 1.0 && $retryCount < 3) {
            $newEpsilon = $epsilon * 10; // Increase epsilon by an order of magnitude
            return findSmallestFactors($number, $newEpsilon, $retryCount + 1);
        }

        return $result;
    }
}

if (!function_exists('divideWithRemainder')) {
    /**
     * Divides a dividend by a divisor and returns the quotient and the remaining dividend and divisor.
     *
     * @param array $input An array containing [dividend, divisor]
     * @return array An array containing [quotient, [remaining_dividend, remaining_divisor]]
     */
    function divideWithRemainder(array $input): array
    {
        $dividend = $input[0];
        $divisor = $input[1];

        if ($divisor == 0) {
            return [0, [$dividend, $divisor]];
        }

        // Calculate the quotient (integer division)
        $quotient = intdiv($dividend, $divisor);

        // Calculate the remainder
        $remainder = $dividend % $divisor;

        // Return the quotient and the remaining dividend and divisor
        return [$quotient, [$remainder, $divisor]];
    }
}
