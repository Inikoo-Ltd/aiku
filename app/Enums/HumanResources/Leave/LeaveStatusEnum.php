<?php

namespace App\Enums\HumanResources\Leave;

use App\Enums\EnumHelperTrait;

enum LeaveStatusEnum: string
{
    use EnumHelperTrait;

    case PENDING  = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public static function labels(): array
    {
        return [
            'pending'  => __('Pending'),
            'approved' => __('Approved'),
            'rejected' => __('Rejected'),
        ];
    }

    public static function colors(): array
    {
        return [
            'pending'  => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'pending'  => [
                'tooltip' => __('Pending'),
                'icon'    => 'fal fa-clock',
                'class'   => 'text-yellow-500',
            ],
            'approved' => [
                'tooltip' => __('Approved'),
                'icon'    => 'fal fa-check-circle',
                'class'   => 'text-green-500',
            ],
            'rejected' => [
                'tooltip' => __('Rejected'),
                'icon'    => 'fal fa-times-circle',
                'class'   => 'text-red-500',
            ],
        ];
    }

    public function color(): string
    {
        return self::colors()[$this->value] ?? 'gray';
    }
}
