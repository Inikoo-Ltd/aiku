<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\ShoppingAssistant;

use App\Enums\EnumHelperTrait;

enum ShoppingAssistantHandOverRouteEnum: string
{
    use EnumHelperTrait;

    case LIVE_CHAT = 'live_chat';
    case EMAIL_CHAT = 'email_chat';
    case WEB_CHAT = 'web_chat';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::LIVE_CHAT->value  => __('Live chat now'),
            self::EMAIL_CHAT->value => __('Email chat, answered later'),
            self::WEB_CHAT->value   => __('Website chat, answered later (no mailbox)'),
        ];
    }
}
