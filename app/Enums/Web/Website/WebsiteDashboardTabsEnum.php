<?php

namespace App\Enums\Web\Website;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum WebsiteDashboardTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case CRAWLS = 'crawls';

    public function blueprint(): array
    {
        return match ($this) {
            self::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
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
