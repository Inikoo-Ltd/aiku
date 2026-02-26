<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 26 Feb 2026 11:16:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Enums\UI\CRM;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ProspectsMailshotsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SETTINGS  = 'settings';
    case MAILSHOTS = 'mailshots';

    public function blueprint(): array
    {
        return match ($this) {
            ProspectsMailshotsTabsEnum::SETTINGS => [
                'title' => __('Settings'),
                'icon'  => 'fal fa-cog',
            ],
            ProspectsMailshotsTabsEnum::MAILSHOTS => [
                'title' => __('Mailshots'),
                'icon'  => 'fal fa-paper-plane',
            ],
        };
    }
}
