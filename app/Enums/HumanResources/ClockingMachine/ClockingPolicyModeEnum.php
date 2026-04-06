<?php

namespace App\Enums\HumanResources\ClockingMachine;

use App\Enums\EnumHelperTrait;

enum ClockingPolicyModeEnum: string
{
    use EnumHelperTrait;

    case ONSITE = 'onsite';
    case REMOTE = 'remote';
    case HYBRID = 'hybrid';

    public static function labels(): array
    {
        return [
            'onsite' => __('Onsite'),
            'remote' => __('Remote'),
            'hybrid' => __('Hybrid'),
        ];
    }
}
