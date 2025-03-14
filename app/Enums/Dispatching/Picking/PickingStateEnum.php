<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 05:07:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Dispatching\Picking;

use App\Enums\EnumHelperTrait;

enum PickingStateEnum: string
{
    use EnumHelperTrait;

    case QUEUED = 'queued';
    case PICKING = 'picking';
    case PICKING_BLOCKED = 'picking-blocked';
    case DONE = 'done';

    public static function labels($forElements = false): array
    {
        return [
            'queued'          => __('Queued'),
            'picking'         => __('Picking'),
            'picking-blocked' => __('Picking Blocked'),
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
            'picking'         => [
                'tooltip' => __('Picking'),
                'icon'    => 'fal fa-hand-paper',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'picking-blocked' => [
                'tooltip' => __('Picking Blocked'),
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
