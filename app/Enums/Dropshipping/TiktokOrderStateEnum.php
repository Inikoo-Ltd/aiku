<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jan 2024 15:25:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Dropshipping;

use App\Enums\EnumHelperTrait;

enum TiktokOrderStateEnum: string
{
    use EnumHelperTrait;

    case OPEN      = 'waiting_shipment';
    case HOLD       = 'hold';
    case INCOMPLETE       = 'incomplete';
    case DISPATCHED       = 'dispatched';

    public static function labels(): array
    {
        return [
            'open'               => __('Open'),
            'hold'                => __('Hold'),
            'incomplete'                => __('Incomplete'),
            'dispatched'                => __('Dispatched')
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'open' => [
                'tooltip' => __('Open'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-lime-500',
                'color'   => 'lime'
            ],
            'hold' => [
                'tooltip' => __('Hold'),
                'icon'    => 'fal fa-share',
                'class'   => 'text-indigo-400',
                'color'   => 'indigo'
            ],
            'incomplete'  => [
                'tooltip' => __('Incomplete'),
                'icon'    => 'fal fa-spell-check',
                'class'   => 'text-red-500',
                'color'   => 'emerald'
            ],
            'dispatched'  => [
                'tooltip' => __('Dispatched'),
                'icon'    => 'fal fa-spell-check',
                'class'   => 'text-emerald-500',
                'color'   => 'emerald'
            ]
        ];
    }
}
