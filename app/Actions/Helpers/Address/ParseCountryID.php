<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Feb 2024 20:26:45 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Address;

use App\Models\Helpers\Country;
use Exception;
use Lorisleiva\Actions\Concerns\AsAction;

class ParseCountryID
{
    use asAction;
    public function handle($country): int|null
    {
        if ($country != '') {
            try {
                $country = trim((string) $country);
                $lc = mb_strtolower($country);

                if (strlen($country) == 2) {
                    return Country::withTrashed()
                        ->whereRaw('LOWER(code) = ?', [$lc])
                        ->firstOrFail()->id;
                } elseif (strlen($country) == 3) {
                    return Country::withTrashed()
                        ->whereRaw('LOWER(iso3) = ?', [$lc])
                        ->firstOrFail()->id;
                } else {
                    return Country::withTrashed()
                        ->whereRaw('LOWER(name) = ?', [$lc])
                        ->firstOrFail()->id;
                }
            } catch (Exception) {
                abort(404, "Country not found: $country \n");
            }
        }

        return null;
    }
}
