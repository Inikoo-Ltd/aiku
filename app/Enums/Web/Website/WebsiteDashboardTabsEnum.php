<?php

namespace App\Enums\Web\Website;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum WebsiteDashboardTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case EDGE_WEBSITES = 'edge_websites';
    case VISITOR_COUNTRIES = 'visitor_countries';
    case CRAWLS = 'crawls';

    public function blueprint(): array
    {
        return match ($this) {
            self::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            self::EDGE_WEBSITES => [
                'title' => __('Edge by website'),
                'icon'  => 'fal fa-globe',
            ],
            self::VISITOR_COUNTRIES => [
                'title' => __('Visits by country'),
                'icon'  => 'fal fa-flag',
            ],
            self::CRAWLS => [
                'title' => __('Crawls'),
                'icon'  => 'fal fa-spider',
                'type'  => 'icon',
                'align' => 'right',
            ],
        };
    }
}
