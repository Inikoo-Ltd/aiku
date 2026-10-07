<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\UI\Web;

use App\Actions\Web\SearchConsole\UI\IndexSearchConsoleQueries;
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
                'title'   => __('Webpages'),
                'icon'    => 'fal fa-browser',
                'tooltip' => __('Each webpage with its visits from Aiku tracking and its Google Search clicks from Search Console, for the selected period.'),
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
        };
    }
}
