<?php

/*
 * Author: stewicca <wiccaalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\UI\Web;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum WebsiteRestrictedCountryTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case OVERVIEW = 'overview';
    case LOGS = 'logs';

    public function blueprint(): array
    {
        return match ($this) {
            WebsiteRestrictedCountryTabsEnum::OVERVIEW => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-globe',
            ],
            WebsiteRestrictedCountryTabsEnum::LOGS => [
                'title' => __('Log'),
                'icon'  => 'fal fa-list',
            ],
        };
    }
}
