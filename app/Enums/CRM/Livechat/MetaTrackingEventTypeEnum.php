<?php

/*
 * Author: Eka Yudinata <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum MetaTrackingEventTypeEnum: string
{
    use EnumHelperTrait;

    case SENT      = 'sent';
    case DELIVERED = 'delivered';
    case READ      = 'read';
    case FAILED    = 'failed';

    public static function labels(): array
    {
        return [
            'sent'      => __('Sent'),
            'delivered' => __('Delivered'),
            'read'      => __('Read'),
            'failed'    => __('Failed'),
        ];
    }

    public static function typeIcon(): array
    {
        return [
            'sent'      => [
                'tooltip' => __('Sent'),
                'icon'    => 'fal fa-paper-plane',
                'class'   => 'text-gray-500',
            ],
            'delivered' => [
                'tooltip' => __('Delivered'),
                'icon'    => 'fal fa-check-double',
                'class'   => 'text-blue-500',
            ],
            'read'      => [
                'tooltip' => __('Read'),
                'icon'    => 'fal fa-eye',
                'class'   => 'text-green-500',
            ],
            'failed'    => [
                'tooltip' => __('Failed'),
                'icon'    => 'fal fa-exclamation-triangle',
                'class'   => 'text-red-500',
            ],
        ];
    }
}
