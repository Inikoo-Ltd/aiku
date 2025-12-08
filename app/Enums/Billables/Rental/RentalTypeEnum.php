<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:28:32 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Billables\Rental;

use App\Enums\EnumHelperTrait;

enum RentalTypeEnum: string
{
    use EnumHelperTrait;

    case STORAGE = 'storage';
    case SPACE = 'space';

    public static function typeIcon(): array
    {
        return [
            'storage' => [
                'tooltip' => __('Storage'),
                'icon' => 'fal fa-inventory',
                'app' => [
                    'name' => 'inventory',
                    'type' => 'font-awesome-5',
                ],
            ],
            'box' => [
                'tooltip' => __('Space'),
                'icon' => 'fal fa-parking',
                'app' => [
                    'name' => 'parking',
                    'type' => 'font-awesome-5',
                ],
            ],
        ];
    }
}
