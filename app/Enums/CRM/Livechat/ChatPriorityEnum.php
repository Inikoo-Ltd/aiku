<?php

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum ChatPriorityEnum: string
{
    use EnumHelperTrait;

    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';
    case URGENT = 'urgent';

    public static function labels(): array
    {
        return [
            'low' => __('Low'),
            'normal' => __('Normal'),
            'high' => __('High'),
            'urgent' => __('Urgent'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'low' => [
                'tooltip' => __('Low Priority'),
                'icon' => 'fas fa-arrow-down',
                'class' => 'text-gray-400',
                'color' => 'gray',
            ],
            'normal' => [
                'tooltip' => __('Normal Priority'),
                'icon' => 'fas fa-minus',
                'class' => 'text-blue-400',
                'color' => 'blue',
            ],
            'high' => [
                'tooltip' => __('High Priority'),
                'icon' => 'fas fa-arrow-up',
                'class' => 'text-orange-500',
                'color' => 'orange',
            ],
            'urgent' => [
                'tooltip' => __('Urgent Priority'),
                'icon' => 'fas fa-exclamation-triangle',
                'class' => 'text-red-500',
                'color' => 'red',
            ],
        ];
    }

    public static function count(): array
    {
        // TODO: Implement count logic based on your stats
        return [
            'low' => 0,
            'normal' => 0,
            'high' => 0,
            'urgent' => 0,
        ];
    }

    public function isHigh(): bool
    {
        return $this === self::HIGH || $this === self::URGENT;
    }

    public function isUrgent(): bool
    {
        return $this === self::URGENT;
    }
}
