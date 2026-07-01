<?php

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum ChatKnowledgeSourceStatusEnum: string
{
    use EnumHelperTrait;

    case PENDING = 'pending';
    case READY = 'ready';
    case UNSUPPORTED = 'unsupported';
    case FAILED = 'failed';

    public static function labels(): array
    {
        return [
            'pending'     => __('Pending'),
            'ready'       => __('Ready'),
            'unsupported' => __('Unsupported format'),
            'failed'      => __('Failed'),
        ];
    }
}
