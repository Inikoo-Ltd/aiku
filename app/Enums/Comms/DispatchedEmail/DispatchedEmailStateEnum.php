<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:10:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\DispatchedEmail;

use App\Enums\EnumHelperTrait;

enum DispatchedEmailStateEnum: string
{
    use EnumHelperTrait;

    case READY = 'ready';

    //case SENT_TO_PROVIDER = 'sent_to_provider'; // <-- no used

    case ERROR = 'error'; // Sent to the provider results in error
    case SENT = 'sent'; // Sent to provider ok


    case REJECTED_BY_PROVIDER = 'rejected_by_provider';  //  but determined that it contained a virus and didn’t attempt to deliver it to the recipient’s mail server.
    case DELAY = 'delay'; //The email couldn't be delivered to the recipient’s mail server because a temporary issue occurred. Delivery delays can occur, for example, when the recipient's inbox is full, or when the receiving email server experiences a transient issue.


    case DELIVERED = 'delivered'; //Amazon SES successfully delivered the email to the recipient's mail server.


    case HARD_BOUNCE = 'hard_bounce';
    case SOFT_BOUNCE = 'soft_bounce';

    case OPENED = 'opened';
    case CLICKED = 'clicked';
    case SPAM = 'spam';
    case UNSUBSCRIBED = 'unsubscribed';




    public static function labels(): array
    {
        return [
            'ready'                => __('Ready to send'),
            'error'                => __('Error, count not send'),
            'rejected_by_provider' => __('Rejected'),
            'sent'                 => __('Sent'),
            'delivered'            => __('Delivered'),
            'hard_bounce'          => __('Hard bounce'),
            'soft_bounce'          => __('Soft bounce'),
            'opened'               => __('Opened'),
            'clicked'              => __('Clicked'),
            'spam'                 => __('Spam'),
            'unsubscribed'         => __('Unsubscribed'),
            'delay'                => __('Delay'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'ready'                => [

                'tooltip' => __('Ready'),
                'icon'    => 'fal fa-spell-check',
                'class'   => 'text-indigo-500'


            ],

            'error'                => [

                'tooltip' => __('Error'),
                'icon'    => 'fal fa-exclamation-circle',
                'class'   => 'text-red-500'


            ],
            'rejected_by_provider' => [

                'tooltip' => __('Reject, email has a virus'),
                'icon'    => 'fal fa-virus',
                'class'   => 'text-red-500'


            ],
            'sent'                 => [

                'tooltip' => __('sent'),
                'icon'    => 'fal fa-paper-plane',
                'class'   => 'text-green-600 animate-pulse'

            ],
            'delivered'            => [

                'tooltip' => __('delivered'),
                'icon'    => 'fal fa-inbox-in',

            ],
            'hard_bounce'          => [

                'tooltip' => __('hand bounce'),
                'icon'    => 'fal fa-exclamation-triangle',
                'class'   => 'text-red-500'

            ],

            'soft_bounce' => [

                'tooltip' => __('soft bounce'),
                'icon'    => 'fal fa-square',
                'class'   => 'text-orange-500'

            ],

            'opened'  => [
                'tooltip' => __('opened'),
                'icon'    => 'fal fa-envelope-open',
            ],
            'clicked' => [
                'tooltip' => __('clicked'),
                'icon'    => 'fal fa-mouse-pointer',
            ],

            'spam' => [
                'tooltip' => __('spam (complain)'),
                'icon'    => 'fal fa-dumpster',
                'class'   => 'text-red-500'
            ],

            'unsubscribed' => [
                'tooltip' => __('Unsubscribed'),
                'icon'    => 'fal fa-hand-paper',
                'class'   => 'text-red-500'
            ],

            'delay' => [
                'tooltip' => __('Delay'),
                'icon'    => 'fal fa-hand-paper',
                'class'   => 'text-red-500'
            ],

        ];
    }
}
