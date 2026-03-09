<?php

namespace App\Enums\HumanResources\Leave;

use App\Enums\EnumHelperTrait;

enum LeaveColorEnum: string
{
    use EnumHelperTrait;

    case GREEN = 'green';
    case ORANGE = 'orange';
    case BLACK = 'black';
    case PURPLE = 'purple';
    case PINK = 'pink';
    case CYAN = 'cyan';
    case INDIGO = 'indigo';

    public static function labels(): array
    {
        return [
            'green'   => __('Green'),
            'orange'  => __('Orange'),
            'black'   => __('Black'),
            'purple'  => __('Purple'),
            'pink'    => __('Pink'),
            'cyan'    => __('Cyan'),
            'indigo'  => __('Indigo'),
        ];
    }

    public static function hexColors(): array
    {
        return [
            'green'   => '#16A34A',
            'orange'  => '#EA580C',
            'black'   => '#000000',
            'purple'  => '#9333EA',
            'pink'    => '#DB2777',
            'cyan'    => '#0891B2',
            'indigo'  => '#4F46E5',
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value] ?? '';
    }

    public function hexColor(): string
    {
        return self::hexColors()[$this->value] ?? '#4F46E5';
    }
}
