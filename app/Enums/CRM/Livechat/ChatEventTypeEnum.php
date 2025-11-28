<?php

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum ChatEventTypeEnum: string
{
    use EnumHelperTrait;

    case OPEN = 'open';
    case AI_REPLY = 'ai_reply';
    case TRANSFER = 'transfer_request';
    case TRANSFER_ACCEPT = 'transfer_accept';
    case TRANSFER_REJECT = 'transfer_reject';
    case TRANSLATE_MESSAGE = 'translate_message';

    case TRANSFER_TO_AGENT = 'transfer_to_agent';

    case ASSIGNMENT_TO_SELF = 'assignment_to_self';

    case CLOSE = 'close';
    case RATING = 'rating';
    case NOTE = 'note';
    case REPLY = 'reply';
    case SEND = 'send';

    public static function labels(): array
    {
        return [
            'open' => __('Chat Opened'),
            'ai_reply' => __('AI Reply'),
            'transfer_request' => __('Transfer Request'),
            'transfer_accept' => __('Transfer Accepted'),
            'transfer_reject' => __('Transfer Rejected'),
            'translate_message' => __('Message Translated'),
            'close' => __('Chat Closed'),
            'rating' => __('Rating Submitted'),
            'note' => __('Note Added'),
            'reply'=> __('Reply'),
            'send'=> __('Send'),
            'transfer_to_agent'=> __('Transfer to Agent'),
            'Assignment_to_self'=> __('Assignment to Self'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'open' => [
                'tooltip' => __('Chat Opened'),
                'icon' => 'fas fa-door-open',
                'class' => 'text-green-500',
            ],
            'ai_reply' => [
                'tooltip' => __('AI Reply'),
                'icon' => 'fas fa-robot',
                'class' => 'text-blue-500',
            ],
            'transfer_request' => [
                'tooltip' => __('Transfer Request'),
                'icon' => 'fas fa-share-alt',
                'class' => 'text-orange-500',
            ],
            'transfer_accept' => [
                'tooltip' => __('Transfer Accepted'),
                'icon' => 'fas fa-check-circle',
                'class' => 'text-green-500',
            ],
            'transfer_reject' => [
                'tooltip' => __('Transfer Rejected'),
                'icon' => 'fas fa-times-circle',
                'class' => 'text-red-500',
            ],
            'translate_message' => [
                'tooltip' => __('Message Translated'),
                'icon' => 'fas fa-language',
                'class' => 'text-purple-500',
            ],
            'close' => [
                'tooltip' => __('Chat Closed'),
                'icon' => 'fas fa-door-closed',
                'class' => 'text-gray-500',
            ],
            'rating' => [
                'tooltip' => __('Rating Submitted'),
                'icon' => 'fas fa-star',
                'class' => 'text-yellow-500',
            ],
            'note' => [
                'tooltip' => __('Note Added'),
                'icon' => 'fas fa-sticky-note',
                'class' => 'text-indigo-500',
            ],
            'reply'=> [
                'tooltip'=> __('Message Reply'),
                'icon'=> 'fas fa-reply',
                'class'=> 'text-gray-500',
            ],
            'send'=> [
                'tooltip'=> __('Message Sent'),
                'icon'=> 'fas fa-paper-plane',
                'class'=> 'text-blue-500',
            ],

        ];
    }
}
