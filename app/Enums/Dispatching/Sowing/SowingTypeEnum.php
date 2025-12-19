<?php

/*
 * author Oggie Sutrisna
 * created on 19-12-2025
 * copyright 2025
*/

namespace App\Enums\Dispatching\Sowing;

use App\Enums\EnumHelperTrait;

enum SowingTypeEnum: string
{
    use EnumHelperTrait;

    case SOW = 'sow';
    case NOT_SOW = 'not-sow';

    public static function labels(): array
    {
        return [
            'sow'     => __('Received/Returned'),
            'not-sow' => __('Not Received/Returned'),
        ];
    }
}
