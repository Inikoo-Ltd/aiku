<?php

namespace App\Enums\HumanResources\Overtime;

use App\Enums\EnumHelperTrait;

enum OvertimeCategoryEnum: string
{
    use EnumHelperTrait;

    case OVERTIME   = 'overtime';
    case FLEXI_TIME = 'flexi_time';
    case WORKING    = 'working_time';
    case OTHER      = 'other';

    public static function labels(): array
    {
        return [
            'overtime'     => __('Overtime'),
            'flexi_time'   => __('Flexi time'),
            'working_time' => __('Working time'),
            'other'        => __('Other'),
        ];
    }
}
