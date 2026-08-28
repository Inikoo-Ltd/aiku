<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Jun 2025 18:47:17 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\Web\Website\Analytics\TrackWebsiteVisitorActivity;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Helpers\WebsiteSearchLog;
use App\Models\Web\Website;

trait WithWebsiteAnalyticsSubNavigation
{
    protected function getWebsiteAnalyticsNavigation(Website $website): array
    {
        $shop = $website->shop;
        if ($shop->type == ShopTypeEnum::FULFILMENT) {
            return $this->getFulfilmentWebpageNavigation($website);
        }


        return array_values(array_filter([

            [
                "isAnchor" => true,
                "label"    => __("Dashboard"),

                "route"    => [
                    "name"       => "grp.org.shops.show.web.analytics.dashboard",
                    "parameters" => [$shop->organisation->slug, $shop->slug, $website->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-analytics"],
                    "tooltip" => __("Website analytics dashboard"),
                ],
            ],
            $this->showLiveVisitors() ? [
                "number"   => $this->getLiveVisitorsCount($website),
                "label"    => __("Live Visitors"),
                "route"    => [
                    "name"       => "grp.org.shops.show.web.analytics.live_users",
                    "parameters" => [$shop->organisation->slug, $shop->slug, $website->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-broadcast-tower"],
                    "tooltip" => __("Visitors on the website right now"),
                ],
            ] : null,
            [
                "number"   => WebsiteSearchLog::where('website_id', $website->id)->count(),
                "label"    => __("Search"),
                "route"    => [
                    "name"       => "grp.org.shops.show.web.analytics.search",
                    "parameters" => [$shop->organisation->slug, $shop->slug, $website->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-search"],
                    "tooltip" => __("Website search insights"),
                ],
            ],
            [
                "label"    => __("Opportunities"),
                "route"    => [
                    "name"       => "grp.org.shops.show.web.analytics.search.opportunities",
                    "parameters" => [$shop->organisation->slug, $shop->slug, $website->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-lightbulb"],
                    "tooltip" => __("What customers searched for and did not find"),
                ],
            ],

        ]));
    }

    protected function getLiveVisitorsCount(Website $website): int
    {
        $counts = TrackWebsiteVisitorActivity::make()->getCounts($website);

        return $counts['logged_in'] + $counts['logged_out'];
    }

    protected function showLiveVisitors(): bool
    {
        return (bool) config('iris.analytics.live_visitors');
    }

    protected function getFulfilmentWebpageNavigation(Website $website): array
    {
        $shop       = $website->shop;
        $fulfilment = $shop->fulfilment;


        return array_values(array_filter([
            [
                "isAnchor" => true,
                "label"    => __("Dashboard"),

                "route"    => [
                    "name"       => "grp.org.fulfilments.show.web.analytics.dashboard",
                    "parameters" => [$shop->organisation->slug, $fulfilment->slug, $website->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-analytics"],
                    "tooltip" => __("Website analytics dashboard"),
                ],

            ],
            $this->showLiveVisitors() ? [
                "number"   => $this->getLiveVisitorsCount($website),
                "label"    => __("Live Visitors"),
                "route"    => [
                    "name"       => "grp.org.fulfilments.show.web.analytics.live_users",
                    "parameters" => [$shop->organisation->slug, $fulfilment->slug, $website->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-broadcast-tower"],
                    "tooltip" => __("Visitors on the website right now"),
                ],
            ] : null,
        ]));
    }
}
