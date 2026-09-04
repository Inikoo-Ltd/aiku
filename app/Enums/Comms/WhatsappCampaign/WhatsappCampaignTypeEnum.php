<?php

namespace App\Enums\Comms\WhatsappCampaign;

use App\Enums\EnumHelperTrait;

enum WhatsappCampaignTypeEnum: string
{
    use EnumHelperTrait;

    case NEWSLETTER = 'newsletter';

    public static function labels(): array
    {
        return [
            'newsletter'     => __('Newsletter'),
        ];
    }
}
