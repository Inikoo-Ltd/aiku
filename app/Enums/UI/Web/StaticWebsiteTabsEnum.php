<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Web;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum StaticWebsiteTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case WEBPAGES = 'webpages';
    case COMPONENTS = 'components';
    case SNAPSHOTS = 'snapshots';

    public function blueprint(): array
    {
        return match ($this) {
            StaticWebsiteTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            StaticWebsiteTabsEnum::WEBPAGES => [
                'title' => __('Webpages'),
                'icon'  => 'fal fa-browser',
            ],
            StaticWebsiteTabsEnum::COMPONENTS => [
                'title' => __('Shared parts'),
                'icon'  => 'fal fa-puzzle-piece',
            ],
            StaticWebsiteTabsEnum::SNAPSHOTS => [
                'title' => __('Snapshots'),
                'icon'  => 'fal fa-history',
            ],
        };
    }
}
