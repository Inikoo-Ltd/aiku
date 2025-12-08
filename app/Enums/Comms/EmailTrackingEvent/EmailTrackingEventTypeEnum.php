<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:10:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\EmailTrackingEvent;

use App\Enums\EnumHelperTrait;

enum EmailTrackingEventTypeEnum: string
{
    use EnumHelperTrait;

    case SENT = 'sent';
    case DECLINED_BY_PROVIDER = 'declined_by_provider';
    case DELIVERED = 'delivered';
    case OPENED = 'opened';
    case CLICKED = 'clicked';
    case SOFT_BOUNCE = 'soft_bounce';
    case HARD_BOUNCE = 'hard_bounce';
    case MARKED_AS_SPAM = 'marked_as_spam';
    case ERROR = 'error';
    case DELAY = 'delay';

    public static function typeIcon(): array
    {
        return [
            'sent' => [
                'tooltip' => __('Sent'),
                'icon' => 'fal fa-paper-plane',
            ],
            'declined_by_provider' => [
                'tooltip' => __('Declined by provider'),
                'icon' => 'fal fa-ban',
            ],
            'delivered' => [
                'tooltip' => __('Delivered'),
                'icon' => 'fal fa-inbox-in',
            ],
            'opened' => [
                'tooltip' => __('Opened'),
                'icon' => 'fal fa-envelope-open',
            ],
            'clicked' => [
                'tooltip' => __('Clicked'),
                'icon' => 'fal fa-mouse-pointer',
            ],
            'soft_bounce' => [
                'tooltip' => __('Soft bounce'),
                'icon' => 'fal fa-square',
            ],
            'hard_bounce' => [
                'tooltip' => __('Hard bounce'),
                'icon' => 'fal fa-exclamation-triangle',
            ],
            'marked_as_spam' => [
                'tooltip' => __('Marked as spam'),
                'icon' => 'fal fa-dumpster',
            ],
            'error' => [
                'tooltip' => __('Error'),
                'icon' => 'fal fa-exclamation-circle',
            ],
            'delay' => [
                'tooltip' => __('Delay'),
                'icon' => 'fal fa-clock',
            ],
        ];
    }
}
