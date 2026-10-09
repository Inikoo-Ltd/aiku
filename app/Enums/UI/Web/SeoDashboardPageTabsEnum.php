<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\UI\Web;

use App\Actions\Web\Website\PruneWebsitePageViews;
use App\Actions\Web\Website\PruneWebsiteVisitors;
use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

/**
 * The tabs at the top of the SEO dashboard. Overview holds the performance cards and the table tabs
 * of `SeoDashboardTabsEnum`, which use the `table` query parameter instead of `tab`.
 */
enum SeoDashboardPageTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case OVERVIEW      = 'overview';
    case VISITORS      = 'visitors';
    case PAGE_VIEWS    = 'page_views';
    case MISSING_PAGES = 'missing_pages';
    case API_USAGE     = 'api_usage';

    public function blueprint(): array
    {
        return match ($this) {
            SeoDashboardPageTabsEnum::OVERVIEW => [
                'title'   => __('Overview'),
                'icon'    => 'fal fa-tachometer-alt-fast',
                'tooltip' => __('Traffic, conversions, Google Search and page speed of the website for the selected interval, with the webpages behind them.'),
            ],
            SeoDashboardPageTabsEnum::VISITORS => [
                'title'   => __('Visitors'),
                'icon'    => 'fal fa-users',
                'tooltip' => __('Every visitor of the last :days days with where they came from, from Aiku tracking.', ['days' => PruneWebsiteVisitors::RETENTION_DAYS]),
            ],
            SeoDashboardPageTabsEnum::PAGE_VIEWS => [
                'title'   => __('Page views'),
                'icon'    => 'fal fa-eye',
                'tooltip' => __('Every page view of the last :days days, from Aiku tracking.', ['days' => PruneWebsitePageViews::RETENTION_DAYS]),
            ],
            SeoDashboardPageTabsEnum::MISSING_PAGES => [
                'title'   => __('Missing pages'),
                'icon'    => 'fal fa-unlink',
                'tooltip' => __('Paths people opened that have no page (404), by hits, with the backlinks pointing at them and a Create redirect action.'),
            ],
            SeoDashboardPageTabsEnum::API_USAGE => [
                'title'   => __('API usage'),
                'icon'    => 'fal fa-tachometer-alt',
                'tooltip' => __('What the paid SEO APIs cost this month for all shops together, against the monthly budget. Per calendar month.'),
            ],
        };
    }
}
