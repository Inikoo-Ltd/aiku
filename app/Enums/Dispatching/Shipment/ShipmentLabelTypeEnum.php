<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 11-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Enums\Dispatching\Shipment;

use App\Enums\EnumHelperTrait;

enum ShipmentLabelTypeEnum: string
{
    use EnumHelperTrait;

    case HTML = 'html';
    case PDF = 'pdf';
    case NA = 'na';

    public static function labels(): array
    {
        return [
            self::HTML->value => __('HTML'),
            self::PDF->value  => __('PDF'),
            self::NA->value   => __('Not Applicable'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            self::HTML->value => [
                'tooltip' => __('HTML'),
                'icon'    => 'fal fa-code',
                'class'   => 'text-blue-500'
            ],
            self::PDF->value => [
                'tooltip' => __('PDF'),
                'icon'    => 'fal fa-file-pdf',
                'class'   => 'text-red-500'
            ],
            self::NA->value => [
                'tooltip' => __('Not Applicable'),
                'icon'    => 'fal fa-ban',
                'class'   => 'text-gray-500'
            ]
        ];
    }
}
