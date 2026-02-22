<?php

namespace App\Enums\HumanResources\Overtime;

use App\Enums\EnumHelperTrait;

enum OvertimeCompensationTypeEnum: string
{
    use EnumHelperTrait;

    case PAID         = 'paid';
    case TIME_IN_LIEU = 'time_in_lieu';
    case UNPAID       = 'unpaid';

    public static function labels(): array
    {
        return [
            'paid'         => __('Paid'),
            'time_in_lieu' => __('Time in lieu'),
            'unpaid'       => __('Unpaid'),
        ];
    }
}
