<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Dec 2024 12:56:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Dispatching\Packing;

use App\Enums\EnumHelperTrait;

enum PackingStateEnum: string
{
    use EnumHelperTrait;
 //TODO: unused , delete
    case QUEUED = 'queued';
    case PACKING = 'packing';
    case PACKING_BLOCKED = 'packing-blocked';
    case DONE = 'done';

    public static function labels($forElements = false): array
    {
        return [
            'queued'          => __('Queued'),
            'packing'         => __('Packing'),
            'packing-blocked' => __('Packing Blocked'),
            'done'            => __('Done'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'queued'           => [
                'tooltip' => __('Queued'),
                'icon'    => 'fal fa-pause-circle',
                'class'   => 'text-gray-500',  // Color for normal icon (Aiku)
                'color'   => 'gray',  // Color for box (Retina)
                'app'     => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5'
                ]
            ],
            'packing'         => [
                'tooltip' => __('Packing'),
                'icon'    => 'fal fa-hand-paper',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'packing-blocked' => [
                'tooltip' => __('Packing Blocked'),
                'icon'    => 'fal fa-hand-paper',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],

            'done' => [
                'tooltip' => __('Done'),
                'icon'    => 'fal fa-box-check',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'times',
                    'type' => 'font-awesome-5'
                ]
            ],
        ];
    }

}
