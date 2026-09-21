<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Enums\Helpers\Ticket;

use App\Enums\EnumHelperTrait;

enum TicketSourceChannelEnum: string
{
    use EnumHelperTrait;

    case WHATSAPP = 'whatsapp';
    case EMAIL = 'email';
    case WEBSITE = 'website';

    public static function labels(): array
    {
        return [
            'whatsapp' => __('WhatsApp'),
            'email'    => __('Email'),
            'website'  => __('Web chat'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'whatsapp' => [
                'tooltip' => __('WhatsApp'),
                'icon'    => 'fab fa-whatsapp',
                'class'   => 'text-green-500',
                'color'   => 'green',
            ],
            'email'    => [
                'tooltip' => __('Email'),
                'icon'    => 'fal fa-envelope',
                'class'   => 'text-blue-500',
                'color'   => 'blue',
            ],
            'website'  => [
                'tooltip' => __('Web chat'),
                'icon'    => 'fal fa-comment',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
            ],
        ];
    }
}
