<?php

/*
 * Author: Andi Ferdiawan
 * Created: Wed, 08 Jul 2026 15:10:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Enums\Dispatching\DeliveryNoteLeaflet;

use App\Enums\EnumHelperTrait;

enum DeliveryNoteLeafletStateEnum: string
{
    use EnumHelperTrait;

    case PENDING_PRINT = 'pending_print';
    case PRINTED = 'printed';
    case INCLUDED = 'included';

    public static function labels(): array
    {
        return [
            'pending_print' => __('Pending Print'),
            'printed'       => __('Printed'),
            'included'      => __('Included'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'pending_print' => [
                'tooltip' => __('Pending print'),
                'icon'    => 'fal fa-print-slash',
                'class'   => 'text-red-500',
                'color'   => 'red',
                'app'     => [
                    'name' => 'print-slash',
                    'type' => 'font-awesome-5'
                ]
            ],
            'printed'       => [
                'tooltip' => __('Printed'),
                'icon'    => 'fal fa-print',
                'class'   => 'text-green-500',
                'color'   => 'green',
                'app'     => [
                    'name' => 'print',
                    'type' => 'font-awesome-5'
                ]
            ],
            'included'      => [
                'tooltip' => __('Included in package'),
                'icon'    => 'fal fa-box-check',
                'class'   => 'text-green-600',
                'color'   => 'green',
                'app'     => [
                    'name' => 'box-check',
                    'type' => 'font-awesome-5'
                ]
            ],
        ];
    }
}
