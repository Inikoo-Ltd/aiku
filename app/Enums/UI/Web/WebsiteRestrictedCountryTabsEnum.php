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

    case RESTRICTED_COUNTRIES = 'restricted_countries';
    case LOGS = 'logs';

    public function blueprint(): array
    {
        return match ($this) {
            WebsiteRestrictedCountryTabsEnum::RESTRICTED_COUNTRIES => [
                'title' => __('Restricted Countries'),
                'icon'  => 'fal fa-ban',
            ],
            WebsiteRestrictedCountryTabsEnum::LOGS => [
                'title' => __('Log'),
                'icon'  => 'fal fa-list',
            ],
        };
    }
}
