<?php

/*
 * author Oggie Sutrisna
 * created on 19-12-2025
 * copyright 2025
*/

namespace App\Enums\Dispatching\Sowing;

use App\Enums\EnumHelperTrait;

enum SowingEngineEnum: string
{
    use EnumHelperTrait;

    case AIKU = 'aiku';
    case AURORA = 'aurora';

    public static function labels(): array
    {
        return [
            'aiku'   => __('Aiku'),
            'aurora' => __('Aurora'),
        ];
    }
}
