<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 05:07:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Dispatching\Picking;

use App\Enums\EnumHelperTrait;

enum PickingStatusEnum: string
{
    use EnumHelperTrait;
//TODO: Unused, delete
    case PROCESSING = 'processing';
    case PICKED = 'picked';
    case PARTIALLY_PICKED = 'partially-picked';
    case NOT_PICKED = 'not_picked';
    case CANCELLED = 'cancelled';

    public static function labels($forElements = false): array
    {
        return [
            'processing'          => __('Processing'),
            'picked'              => __('Picked'),
            'picked-blocked'      => __('Picked Partially'),
            'not_picked'          => __('Not Picked'),
            'cancelled'           => __('Cancelled'),

        ];
    }

    public static function stateIcon(): array
    {
        return [
            'processing'           => [
                'tooltip' => __('Processing'),
                'icon'    => 'fal fa-pause-circle',
                'class'   => 'text-gray-500',  // Color for normal icon (Aiku)
                'color'   => 'gray',  // Color for box (Retina)
                'app'     => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5'
                ]

            ],
            'picked'              => [
                'tooltip' => __('Picked'),
                'icon'    => 'fal fa-hand-paper',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'picked-blocked'      => [
                'tooltip' => __('Picked Partially'),
                'icon'    => 'fal fa-hand-paper',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'not_picked'          => [
                'tooltip' => __('Not Picked'),
                'icon'    => 'fal fa-hand-paper',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'cancelled'           => [
                'tooltip' => __('Cancelled'),
                'icon'    => 'fal fa-hand-paper',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],

        ];
    }

}
