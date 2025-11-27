<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jul 2024 17:22:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Country\UI;

use Lorisleiva\Actions\Concerns\AsObject;

class IsEuropeanUnion
{
    use AsObject;

    public function handle(string $code): bool
    {
        return in_array($code, $this->getEUCountryCodes());
    }


    public static function getEUCountryCodes(): array
    {
        return [
            'AT',
            'BE',
            'BG',
            'HR',
            'CY',
            'CZ',
            'DK',
            'EE',
            'FI',
            'FR',
            'DE',
            'GR',
            'HU',
            'IE',
            'IT',
            'LV',
            'LT',
            'LU',
            'MT',
            'NL',
            'PL',
            'PT',
            'RO',
            'SK',
            'SI',
            'ES',
            'SE'
        ];
    }
}
