<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 17:08:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Fulfilment\StoredItemAuditDelta;

use App\Enums\EnumHelperTrait;

enum StoredItemAuditDeltaTypeEnum: string
{
    use EnumHelperTrait;

    case ADDITION = 'addition';
    case SUBTRACTION = 'subtraction';
    case SET_UP = 'set_up';
    case NO_CHANGE = 'no_change';
    case DELIVERY = 'delivery';

    public static function labels(): array
    {
        return [
            'addition' => __('Addition'),
            'subtraction' => __('Subtraction'),
            'set_up' => __('Set Up'),
            'no_change' => __('No Change'),
            'delivery' => __('Delivery'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'addition' => [
                'tooltip' => __('Addition'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-lime-500',  // Color for normal icon (Aiku)
                'color' => 'lime',  // Color for box (Retina)
                'app' => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5',
                ],
            ],
            'subtraction' => [
                'tooltip' => __('Subtraction'),
                'icon' => 'fal fa-check',
                'class' => 'text-indigo-400',
                'color' => 'indigo',
                'app' => [
                    'name' => 'check',
                    'type' => 'font-awesome-5',
                ],
            ],
            'set_up' => [
                'tooltip' => __('Set Up'),
                'icon' => 'fal fa-check',
                'class' => 'text-indigo-400',
                'color' => 'indigo',
                'app' => [
                    'name' => 'check',
                    'type' => 'font-awesome-5',
                ],
            ],
            'no_change' => [
                'tooltip' => __('No Change'),
                'icon' => 'fal fa-check',
                'class' => 'text-indigo-400',
                'color' => 'indigo',
                'app' => [
                    'name' => 'check',
                    'type' => 'font-awesome-5',
                ],
            ],
            'delivery' => [
                'tooltip' => __('Delivery'),
                'icon' => 'fal fa-check',
                'class' => 'text-indigo-400',
                'color' => 'indigo',
                'app' => [
                    'name' => 'check',
                    'type' => 'font-awesome-5',
                ],
            ],
        ];
    }
}
