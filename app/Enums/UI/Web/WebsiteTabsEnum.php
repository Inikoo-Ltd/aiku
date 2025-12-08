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

    case SHOWCASE = 'showcase';
    case EXTERNAL_LINKS = 'external_links';
    case REDIRECTS = 'redirects';
    case CHANGELOG = 'changelog';

    public function blueprint(): array
    {
        return match ($this) {
            WebsiteTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon' => 'fal fa-tachometer-alt-fast',
            ],
            WebsiteTabsEnum::EXTERNAL_LINKS => [
                'title' => __('External links'),
                'icon' => 'fal fa-external-link',
            ],

            WebsiteTabsEnum::REDIRECTS => [
                'title' => __('Redirects'),
                'icon' => 'fal fa-terminal',
            ],

            WebsiteTabsEnum::CHANGELOG => [
                'title' => __('Changelog'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],
        };
    }
}
