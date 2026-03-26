<?php

namespace App\Enums\HumanResources\Leave;

use App\Enums\EnumHelperTrait;

enum LeaveCategoryEnum: string
{
    use EnumHelperTrait;

    case ANNUAL = 'annual';
    case PERSONAL = 'personal';
    case MEDICAL = 'medical';
    case SPECIAL = 'special';

    case UNPAID = 'unpaid';

    public static function labels(): array
    {
        return [
            'annual' => __('Annual Leave'),
            'personal' => __('Personal Leave'),
            'medical' => __('Medical Leave'),
            'special' => __('Special Leave'),
            'unpaid' => __('Unpaid Leave'),
        ];
    }

    public static function colors(): array
    {
        return [
            'annual' => 'blue',
            'personal' => 'green',
            'medical' => 'yellow',
            'special' => 'purple',
            'unpaid' => 'red',
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value] ?? '';
    }

    public function color(): string
    {
        return self::colors()[$this->value] ?? 'gray';
    }
}
