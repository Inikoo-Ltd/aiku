<?php

namespace App\Enums\Comms\WhatsappSubscriber;

use App\Enums\EnumHelperTrait;

enum WhatsappSubscriberOptInMethodEnum: string
{
    use EnumHelperTrait;

    case WEBSITE = 'website';
    case PHONE_NUMBER_INVITATION = 'phone_number_invitation';

    public static function labels(): array
    {
        return [
            'website'                 => __('Website'),
            'phone_number_invitation' => __('Phone number invitation'),
        ];
    }
}
