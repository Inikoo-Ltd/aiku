<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Enums\UI\Mail;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum CustomerNotificationOutboxesTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case EMAIL = 'email';
    case WHATSAPP = 'whatsapp';

    public function blueprint(): array
    {
        return match ($this) {
            CustomerNotificationOutboxesTabsEnum::EMAIL => [
                'title' => __('Email'),
                'icon'  => 'fal fa-envelope',
            ],
            CustomerNotificationOutboxesTabsEnum::WHATSAPP => [
                'title' => __('WhatsApp'),
                'icon'  => 'fab fa-whatsapp',
            ],
        };
    }
}
