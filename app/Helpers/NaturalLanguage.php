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
}
