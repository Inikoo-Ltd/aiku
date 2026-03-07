<?php

namespace App\Enums\HumanResources\Leave;

use App\Enums\EnumHelperTrait;

enum LeaveTypeEnum: string
{
    use EnumHelperTrait;

    case ANNUAL = 'annual';
    case MEDICAL = 'medical';
    case UNPAID = 'unpaid';
    case HALFDAY_MORNING = 'halfday-morning';
    case HALFDAY_AFTERNOON = 'halfday-afternoon';

    public static function labels(): array
    {
        return [
            'annual'             => __('Annual Leave'),
            'medical'            => __('Medical Leave'),
            'unpaid'             => __('Unpaid Leave'),
            'halfday-morning'    => __('Half Day Morning'),
            'halfday-afternoon'  => __('Half Day Afternoon'),
        ];
    }

    public static function colors(): array
    {
        return [
            'annual'             => 'blue',
            'medical'            => 'yellow',
            'unpaid'             => 'fuchsia',
            'halfday-morning'    => 'green',
            'halfday-afternoon'  => 'green',
        ];
    }

    public function color(): string
    {
        return self::colors()[$this->value] ?? 'gray';
    }
}
