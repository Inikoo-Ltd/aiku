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

    case PICK = 'pick';
    case NOT_PICK = 'not-pick';

    public static function labels(): array
    {
        return [
            'pick'     => __('Pick'),
            'not-pick' => __('Not Pick'),
        ];
    }

}
