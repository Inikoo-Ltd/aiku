<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Jun 2025 18:47:17 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Web\Website;

trait WithWebsiteAnalyticsSubNavigation
{
    protected function getWebsiteAnalyticsNavigation(Website $website): array
    {
        $shop = $website->shop;
        if ($shop->type == ShopTypeEnum::FULFILMENT) {
            return $this->getFulfilmentWebpageNavigation($website);
        }


        return [

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
            [
                "number"   => $website->webStats->number_web_user_requests,
                "label"    => __("Website User Visits"),
                "route"    => [
                    "name"       => "grp.org.shops.show.web.analytics.web_user_requests.index",
                    "parameters" => [$shop->organisation->slug, $shop->slug, $website->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-project-diagram"],
                    "tooltip" => __("Website User Requests"),
                ],
            ],

        ];
    }

    protected function getFulfilmentWebpageNavigation(Website $website): array
    {
        $shop       = $website->shop;
        $fulfilment = $shop->fulfilment;


        return [
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
            [
                "number"   => $website->webStats->number_web_user_requests,
                "label"    => __("Website User Visits"),
                "route"    => [
                    "name"       => "grp.org.fulfilments.show.web.analytics.web_user_requests.index",
                    "parameters" => [$shop->organisation->slug, $fulfilment->slug, $website->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-project-diagram"],
                    "tooltip" => __("Website User Requests"),
                ],
            ],




        ];
    }

}
