<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 19 Jan 2026 15:05:25 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Enums\UI\Mail;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum EmailTemplateTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case TEMPLATES = 'templates';
    case PREVIOUS_MAILSHOTS = 'previous_mailshots';
    case OTHER_STORE_MAILSHOTS = 'other_store_mailshots';

    public function blueprint(): array
    {
        return match ($this) {
            EmailTemplateTabsEnum::TEMPLATES => [
                'title' => __('Templates'),
                'icon'  => 'fal fa-layer-group',
            ],
            EmailTemplateTabsEnum::PREVIOUS_MAILSHOTS => [
                'title' => __('Previous Mailshots'),
                'icon'  => 'fal fa-history',
            ],
            EmailTemplateTabsEnum::OTHER_STORE_MAILSHOTS => [
                'title' => __('Other Store Mailshots'),
                'icon'  => 'fal fa-store-alt',
            ],
        };
    }
}
