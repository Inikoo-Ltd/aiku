<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 May 2024 15:19:54 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Fulfilment\FulfilmentCustomer;

use App\Enums\EnumHelperTrait;
use App\Models\Fulfilment\Fulfilment;

enum FulfilmentCustomerStatusEnum: string
{
    use EnumHelperTrait;

    case NO_RENTAL_AGREEMENT = 'no_rental_agreement';
    case ACTIVE = 'active';
    case UNACCOMPLISHED = 'unaccomplished';
    case INACTIVE = 'inactive';
    case LOST = 'lost';

    public static function labels(): array
    {
        return [
            'no_rental_agreement' => __('No Rental Agreement'),
            'active' => __('Active'),
            'unaccomplished' => __('Unaccomplished'),
            'inactive' => __('Losing'),
            'lost' => __('Lost'),
        ];
    }

    public static function count(Fulfilment $parent): array
    {
        $stats = $parent->stats;

        return [
            'no_rental_agreement' => $stats->number_customers_status_no_rental_agreement,
            'unaccomplished' => $stats->number_customers_status_unaccomplished,
            'active' => $stats->number_customers_status_active,
            'inactive' => $stats->number_customers_status_inactive,
            'lost' => $stats->number_customers_status_lost,
        ];
    }

    public static function statusIcon(): array
    {
        return [
            'no_rental_agreement' => [
                'tooltip' => __('No Rental Agreement'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-lime-500',
                'color' => 'lime',  // Color for box (Retina)
                'app' => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5',
                ],
            ],
            'unaccomplished' => [
                'tooltip' => __('Unaccomplished'),
                'icon' => 'fal fa-skull-cow',
                'class' => 'text-yellow-500',
                'color' => 'yellow',
                'app' => [
                    'name' => 'skull-cow',
                    'type' => 'font-awesome-5',
                ],
            ],
            'inactive' => [
                'tooltip' => __('Inactive'),
                'icon' => 'fal fa-share',
                'class' => 'text-gray-400',
                'color' => 'gray',
                'app' => [
                    'name' => 'share',
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
            'lost' => [
                'tooltip' => __('Lost'),
                'icon' => 'fal fa-times',
                'class' => 'text-red-500',
                'color' => 'red',
                'app' => [
                    'name' => 'times',
                    'type' => 'font-awesome-5',
                ],
            ],
        ];
    }
}
