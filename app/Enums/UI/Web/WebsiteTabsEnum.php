<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:14:32 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Web;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum WebsiteTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE  = 'showcase';
    case EXTERNAL_LINKS  = 'external_links';
    case ANALYTICS = 'analytics';
    case WEB_USERS = 'web_users';
    case CHANGELOG = 'changelog';

    public function blueprint(): array
    {
        return match ($this) {
            WebsiteTabsEnum::SHOWCASE => [
                'title' => __('website'),
                'icon'  => 'fas fa-info-circle',
            ],
            WebsiteTabsEnum::EXTERNAL_LINKS => [
                'title' => __('external links'),
                'icon'  => 'fal fa-external-link',
            ],
            WebsiteTabsEnum::ANALYTICS => [
                'title' => __('analytics'),
                'icon'  => 'fal fa-analytics',
            ],
            WebsiteTabsEnum::WEB_USERS => [
                'title' => __('website users'),
                'icon'  => 'fal fa-terminal',
            ],

            WebsiteTabsEnum::CHANGELOG => [
                'title' => __('changelog'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
        };
    }
}
