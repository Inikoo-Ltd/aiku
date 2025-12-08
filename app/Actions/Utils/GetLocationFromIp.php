<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 12:48:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Utils;

use Lorisleiva\Actions\Concerns\AsAction;
use Stevebauman\Location\Facades\Location as FacadesLocation;

class GetLocationFromIp
{
    use AsAction;

    public function handle(?string $ip): array
    {
        if ($position = FacadesLocation::get($ip)) {
            return [
                $position->countryCode,
                $position->countryName,
                $position->cityName,
            ];
        }

        return [];
    }
}
