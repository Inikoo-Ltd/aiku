<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum ChatPhoneCallStatusEnum: string
{
    use EnumHelperTrait;

    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case AUTO_CLOSED = 'auto_closed';

    public static function labels(): array
    {
        return [
            'in_progress' => __('On the phone'),
            'completed'   => __('Completed'),
            'cancelled'   => __('Cancelled'),
            'auto_closed' => __('Closed automatically'),
        ];
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $case) => [
                'label' => self::labels()[$case->value] ?? $case->value,
                'value' => $case->value,
            ])
            ->toArray();
    }
}
