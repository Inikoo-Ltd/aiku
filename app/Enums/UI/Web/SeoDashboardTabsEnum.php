<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\UI\Web;

use App\Actions\Web\SearchConsole\UI\IndexSearchConsoleQueries;
use App\Actions\Web\WebVital\GetWebsitePageSpeedSummary;
use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum SeoDashboardTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case WEBPAGES             = 'webpages';
    case CONVERSIONS          = 'conversions';
    case SEARCH_QUERIES       = 'search_queries';
    case SEARCH_OPPORTUNITIES = 'search_opportunities';
    case PAGE_SPEED           = 'page_speed';

    public function blueprint(): array
    {
        return match ($this) {
            SeoDashboardTabsEnum::WEBPAGES => [
                'title'   => __('Top pages'),
                'icon'    => 'fal fa-browser',
                'tooltip' => __('Each webpage with its visits from Aiku tracking, its Google Search clicks from Search Console and the domains linking to it, for the selected period and against the period before.'),
            ],
            SeoDashboardTabsEnum::CONVERSIONS => [
                'title'   => __('Conversions'),
                'icon'    => 'fal fa-cash-register',
                'tooltip' => __('Customers who opened the checkout or submitted an order on the website in the selected period, with the revenue of their orders.'),
            ],
            SeoDashboardTabsEnum::SEARCH_QUERIES => [
                'title'   => __('Search queries'),
                'icon'    => 'fal fa-search',
                'tooltip' => __('What people typed in Google before this website appeared in the results, from Search Console. Google leaves out rare queries to protect privacy.'),
            ],
            SeoDashboardTabsEnum::SEARCH_OPPORTUNITIES => [
                'title'   => __('Low CTR queries'),
                'icon'    => 'fal fa-mouse-pointer',
                'tooltip' => __('Queries where this website already shows on page one of Google but few people click: at least :impressions impressions, average position :position or better, and a CTR under :ctr%. A clearer title and meta description on the page usually bring more clicks.', [
                    'impressions' => IndexSearchConsoleQueries::OPPORTUNITY_MIN_IMPRESSIONS,
                    'position'    => IndexSearchConsoleQueries::OPPORTUNITY_MAX_POSITION,
                    'ctr'         => IndexSearchConsoleQueries::OPPORTUNITY_MAX_CTR,
                ]),
            ],
            SeoDashboardTabsEnum::PAGE_SPEED => [
                'title'   => __('Page speed'),
                'icon'    => 'fal fa-tachometer-alt-fast',
                'tooltip' => __('Core Web Vitals of each webpage, measured in visitors\' browsers over the last :days days: how fast the main content shows (LCP), how fast the page reacts (INP) and how much it jumps (CLS). Worst pages first.', ['days' => GetWebsitePageSpeedSummary::VISITOR_DAYS]),
            ],
        };
    }
}
