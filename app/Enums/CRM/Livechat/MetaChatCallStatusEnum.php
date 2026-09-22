<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum MetaChatCallStatusEnum: string
{
    use EnumHelperTrait;

    case RINGING = 'ringing';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case MISSED = 'missed';
    case REJECTED = 'rejected';
    case FAILED = 'failed';

    public static function labels(): array
    {
        return [
            'ringing'     => __('Ringing'),
            'in_progress' => __('On the call'),
            'completed'   => __('Completed'),
            'missed'      => __('Missed'),
            'rejected'    => __('Rejected'),
            'failed'      => __('Failed'),
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
