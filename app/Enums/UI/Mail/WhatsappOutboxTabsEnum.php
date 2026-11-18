<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Enums\UI\Mail;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum WhatsappOutboxTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case MESSAGES = 'messages';
    case BULK_RUNS = 'bulk_runs';

    public function blueprint(): array
    {
        return match ($this) {
            WhatsappOutboxTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            WhatsappOutboxTabsEnum::MESSAGES => [
                'title' => __('Sent messages'),
                'icon'  => 'far fa-paper-plane',
            ],
            WhatsappOutboxTabsEnum::BULK_RUNS => [
                'title' => __('Bulk runs'),
                'icon'  => 'fal fa-mail-bulk',
            ],
        };
    }
}
