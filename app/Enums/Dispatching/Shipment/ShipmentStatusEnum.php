<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Dec 2024 19:12:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Dispatching\Shipment;

use App\Enums\EnumHelperTrait;

enum ShipmentStatusEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case SUCCESS = 'success';
    case FIXED = 'fixed';
    case ERROR = 'error';

    public static function labels(): array
    {
        return [
            'in_process' => __('In process'),
            'success' => __('Success'),
            'fixed' => __('Fixed'),
            'error' => __('Error'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process' => [

                'tooltip' => __('In process'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-indigo-500',

            ],
            'success' => [

                'tooltip' => __('Success'),
                'icon' => 'fal fa-spell-check',
                'class' => 'text-green-500',
            ],
            'fixed' => [

                'tooltip' => __('Fixed'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-green-500',
            ],
            'error' => [

                'tooltip' => __('Error'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-red-500',
            ],
        ];
    }
}
