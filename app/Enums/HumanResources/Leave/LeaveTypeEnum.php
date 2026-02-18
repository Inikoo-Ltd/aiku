<?php

namespace App\Enums\HumanResources\Leave;

use App\Enums\EnumHelperTrait;

enum LeaveTypeEnum: string
{
    use EnumHelperTrait;

    case ANNUAL = 'annual';
    case MEDICAL = 'medical';
    case UNPAID = 'unpaid';

    public static function labels(): array
    {
        return [
            'annual'  => __('Annual Leave'),
            'medical' => __('Medical Leave'),
            'unpaid'  => __('Unpaid Leave'),
        ];
    }

    public static function colors(): array
    {
        return [
            'annual'  => 'blue',
            'medical' => 'red',
            'unpaid'  => 'gray',
        ];
    }

    public function color(): string
    {
        return self::colors()[$this->value] ?? 'gray';
    }
}
