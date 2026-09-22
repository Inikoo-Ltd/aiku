<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum ChatPhoneCallContactTypeEnum: string
{
    use EnumHelperTrait;

    case CUSTOMER = 'customer';
    case GUEST = 'guest';

    public static function labels(): array
    {
        return [
            'customer' => __('Customer'),
            'guest'    => __('Guest'),
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
