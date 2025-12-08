<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:28:32 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Billables\Rental;

use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\Shop;

enum RentalStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case ACTIVE = 'active';
    case DISCONTINUED = 'discontinued';

    public static function labels(): array
    {
        return [
            'in_process' => __('In Process'),
            'active' => __('Active'),
            'discontinued' => __('Discontinued'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process' => [
                'tooltip' => __('In process'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-lime-500',  // Color for normal icon (Aiku)
                'color' => 'lime',  // Color for box (Retina)
                'app' => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5',
                ],
            ],
            'active' => [
                'tooltip' => __('Active'),
                'icon' => 'fal fa-check',
                'class' => 'text-green-500',
                'color' => 'green',
                'app' => [
                    'name' => 'check',
                    'type' => 'font-awesome-5',
                ],
            ],
            'discontinuing' => [
                'tooltip' => __('Discontinuing'),
                'icon' => 'fal fa-exclamation-triangle',
                'class' => 'text-orange-500',
                'color' => 'orange',
                'app' => [
                    'name' => 'exclamation-triangle',
                    'type' => 'font-awesome-5',
                ],
            ],
            'discontinued' => [
                'tooltip' => __('Discontinued'),
                'icon' => 'fal fa-times',
                'class' => 'text-red-500',
                'color' => 'red',
                'app' => [
                    'name' => 'times',
                ],
            ],
        ];
    }

    public static function count(Shop $parent): array
    {
        $stats = $parent->stats;

        return [
            'in_process' => $stats->number_rentals_state_in_process,
            'active' => $stats->number_rentals_state_active,
            'discontinued' => $stats->number_rentals_state_discontinued,
        ];
    }
}
