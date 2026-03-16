<?php

namespace App\Enums\HumanResources\Overtime;

use App\Enums\EnumHelperTrait;

enum OvertimeAllowanceUnitEnum: string
{
    use EnumHelperTrait;

    case MINUTES = 'minutes';
    case HOURS   = 'hours';
    case DAYS    = 'days';

    public static function labels(): array
    {
        return [
            'minutes' => __('Minutes'),
            'hours'   => __('Hours'),
            'days'    => __('Days'),
        ];
    }
}
