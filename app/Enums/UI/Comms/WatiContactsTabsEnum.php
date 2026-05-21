<?php

/*
 * Author: andiferdiawan (https://github.com/andiferdiawan)
 * Created: Wednesday, 21 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, andiferdiawan
 */

namespace App\Enums\UI\Comms;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum WatiContactsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case ALL         = 'all';
    case LINKED      = 'linked';
    case WATI_ONLY   = 'wati_only';
    case NOT_IN_WATI = 'not_in_wati';

    public function blueprint(): array
    {
        return match ($this) {
            WatiContactsTabsEnum::ALL => [
                'title' => __('All'),
                'icon'  => 'fal fa-users',
            ],
            WatiContactsTabsEnum::LINKED => [
                'title' => __('Linked'),
                'icon'  => 'fal fa-link',
            ],
            WatiContactsTabsEnum::WATI_ONLY => [
                'title' => __('Wati Only'),
                'icon'  => 'fab fa-whatsapp',
            ],
            WatiContactsTabsEnum::NOT_IN_WATI => [
                'title' => __('Not in Wati'),
                'icon'  => 'fal fa-user-slash',
                'align' => 'right',
            ],
        };
    }
}
