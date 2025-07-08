<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Aug 2023 11:12:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Helpers;

use Lorisleiva\Actions\Concerns\AsObject;

class NaturalLanguage
{
    use AsObject;

    public function fileSize($size, $precision = 2, $suffix = null): string
    {
        if ($size > 0) {
            $size     = (int)$size;
            $suffixes = array(' bytes', ' KB', ' MB', ' GB', ' TB');

            if ($suffix !== null) {
                // Find the base that corresponds to the provided suffix
                $suffixBase = null;
                foreach ($suffixes as $index => $availableSuffix) {
                    if (trim($availableSuffix) === trim($suffix)) {
                        $suffixBase = $index;
                        break;
                    }
                }

                // If we found a matching suffix, use its base
                if ($suffixBase !== null) {
                    return round($size / pow(1024, $suffixBase), $precision).$suffix;
                }

                // If no matching suffix was found, use the calculated base with the custom suffix
                $base = log($size) / log(1024);
                return round(pow(1024, $base - floor($base)), $precision).$suffix;
            }

            // Default behavior (no custom suffix)
            $base = log($size) / log(1024);
            return round(pow(1024, $base - floor($base)), $precision).$suffixes[floor($base)];
        } else {
            return $size;
        }
    }

    public function weight($weight, $precision = 2, $suffix = null): ?string
    {
        if ($weight > 0) {
            $weight   = (float)$weight;
            $suffixes = array(' g', ' kg', ' t');

            if ($suffix !== null) {
                // Find the base that corresponds to the provided suffix
                $suffixBase = null;
                foreach ($suffixes as $index => $availableSuffix) {
                    if (trim($availableSuffix) === trim($suffix)) {
                        $suffixBase = $index;
                        break;
                    }
                }

                // If we found a matching suffix, use its base
                if ($suffixBase !== null) {
                    $value = $weight / pow(1000, $suffixBase);
                    // Format the value based on whether it's a whole number or not
                    if ($value == (int)$value) {
                        return (int)$value . $suffix;
                    } else {
                        return rtrim(rtrim(number_format($value, $precision, '.', ''), '0'), '.') . $suffix;
                    }
                }

                // If no matching suffix was found, use the calculated base with the custom suffix
                $base = log($weight) / log(1000);
                $value = pow(1000, $base - floor($base));
                if ($value == (int)$value) {
                    return (int)$value . $suffix;
                } else {
                    return rtrim(rtrim(number_format($value, $precision, '.', ''), '0'), '.') . $suffix;
                }
            }

            // Default behavior (no custom suffix)
            // Choose the suffix based on the weight value
            if ($weight >= 1000000) {
                // Convert to tonnes
                $value = $weight / 1000000;
                $suffix = $suffixes[2]; // t
            } elseif ($weight >= 1000) {
                // Convert to kilograms for weights over 1000g
                $value = $weight / 1000;
                $suffix = $suffixes[1]; // kg
            } else {
                // Use grams for weights under 1000g
                $value = $weight;
                $suffix = $suffixes[0]; // g
            }

            // Format the value based on whether it's a whole number or not
            if ($value == (int)$value) {
                return (int)$value . $suffix;
            } else {
                return rtrim(rtrim(number_format($value, $precision, '.', ''), '0'), '.') . $suffix;
            }
        } else {
            return $weight;
        }
    }
}
