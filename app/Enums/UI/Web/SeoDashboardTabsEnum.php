<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\UI\Web;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum SeoDashboardTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case WEBPAGES             = 'webpages';
    case SEARCH_QUERIES       = 'search_queries';
    case SEARCH_OPPORTUNITIES = 'search_opportunities';

    public function blueprint(): array
    {
        return match ($this) {
            SeoDashboardTabsEnum::WEBPAGES => [
                'title' => __('Webpages'),
                'icon'  => 'fal fa-browser',
            ],
            SeoDashboardTabsEnum::SEARCH_QUERIES => [
                'title' => __('Search queries'),
                'icon'  => 'fal fa-search',
            ],
            SeoDashboardTabsEnum::SEARCH_OPPORTUNITIES => [
                'title' => __('Low CTR queries'),
                'icon'  => 'fal fa-mouse-pointer',
            ],
        };
    }
}
