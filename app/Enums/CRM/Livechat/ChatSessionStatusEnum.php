<?php

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum ChatSessionStatusEnum: string
{
    use EnumHelperTrait;

    case ACTIVE      = 'active';
    case WAITING     = 'waiting';
    case RESOLVED    = 'resolved';
    case TRANSFERRED = 'transferred';
    case CLOSED      = 'closed';

    public static function labels(): array
    {
        return [
            'active'      => __('Active'),
            'waiting'     => __('Waiting'),
            'resolved'    => __('Resolved'),
            'transferred' => __('Transferred'),
            'closed'      => __('Closed'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'active' => [
                'tooltip' => __('Active'),
                'icon'    => 'fas fa-comments',
                'class'   => 'text-green-500',
                'color'   => 'green'
            ],
            'waiting' => [
                'tooltip' => __('Waiting'),
                'icon'    => 'fal fa-clock',
                'class'   => 'text-yellow-500',
                'color'   => 'yellow'
            ],
            'resolved' => [
                'tooltip' => __('Resolved'),
                'icon'    => 'fas fa-check-circle',
                'class'   => 'text-blue-500',
                'color'   => 'blue'
            ],
            'transferred' => [
                'tooltip' => __('Transferred'),
                'icon'    => 'fas fa-share-alt',
                'class'   => 'text-purple-500',
                'color'   => 'purple'
            ],
            'closed' => [
                'tooltip' => __('Closed'),
                'icon'    => 'fas fa-times-circle',
                'class'   => 'text-gray-500',
                'color'   => 'gray'
            ],
        ];
    }

    public static function count(): array
    {
        // TODO: Implement count logic based on your stats
        return [
            'active'      => 0,
            'waiting'     => 0,
            'resolved'    => 0,
            'transferred' => 0,
            'closed'      => 0,
        ];
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isWaiting(): bool
    {
        return $this === self::WAITING;
    }

    public function isClosed(): bool
    {
        return $this === self::CLOSED;
    }
}
