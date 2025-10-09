<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 02 Aug 2025 09:46:40 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

trait WithOrganisationSourceShop
{
    public function getOrganisationSourceShop(): array
    {
        return [
            'en' => [
                1 => 1,
                2 => 2,
                3 => 5
            ],
            'es' => [
                3 => 1
            ],
            'fr' => [
                2 => 6,
                3 => 6,
            ],
            'de' => [
                2 => 4,
                3 => 7,
            ],
            'it' => [
                2 => 8,
            ],
            'pl' => [
                2 => 10,
            ],
            'sk' => [
                2 => 12,
            ],
            'pt' => [
                3 => 2,
            ],
            'cs' => [
                2 => 14,
            ],
            'hu' => [
                2 => 16,
            ],
            'nl' => [
                2 => 20,
            ],
            'ro' => [
                2 => 21,
            ],
            'sv' => [
                2 => 23,
            ],
            'hr' => [
                2 => 24,
            ],
            'bg' => [
                2 => 25,
            ]
        ];
    }
}
