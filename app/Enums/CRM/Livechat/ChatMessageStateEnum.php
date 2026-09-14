<?php

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum ChatMessageStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case READY = 'ready';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case READ = 'read';
    case CLICKED = 'clicked';
    case FAILED = 'failed';

    public static function labels(): array
    {
        return [
            'in_process' => __('In process'),
            'ready'      => __('Ready'),
            'sent'       => __('Sent'),
            'delivered'  => __('Delivered'),
            'read'       => __('Read'),
            'clicked'    => __('Clicked'),
            'failed'     => __('Failed'),
        ];
    }
}
