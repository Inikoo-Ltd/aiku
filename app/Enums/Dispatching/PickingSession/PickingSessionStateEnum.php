<?php
/*
 * author Arya Permana - Kirin
 * created on 07-07-2025-18h-24m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\Dispatching\PickingSession;

use App\Enums\EnumHelperTrait;

enum PickingSessionStateEnum: string
{
    use EnumHelperTrait;


    case IN_PROCESS = 'in_process';
    case HISTORIC = 'historic';
    case ACTIVE = 'active';

    public static function labels(): array
    {
        return [
            'in_process' => __('In Process'),
            'historic'     => __('Historic'),
            'active'   => __('Active'),
        ];
    }
}
