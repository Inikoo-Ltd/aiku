<?php

/*
 * author Arya Permana - Kirin
 * created on 23-05-2025-11h-52m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\Dispatching\Picking;

use App\Enums\EnumHelperTrait;

enum PickingTypeEnum: string
{
    use EnumHelperTrait;

    case PICK = 'pick';
    case NOT_PICK = 'not-pick';

    public static function labels(): array
    {
        return [
            'pick'          => __('Pick'),
            'not-pick'         => __('Not Pick'),
        ];
    }

}
