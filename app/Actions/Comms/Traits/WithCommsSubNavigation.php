<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Dec 2024 18:14:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Traits;

use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Group;

trait WithCommsSubNavigation
{
    protected function getCommsNavigation(Shop|Fulfilment|Group $parent): array
    {
        if ($parent instanceof Shop) {
            return $this->getNavigationRouteShops($parent);
        } elseif ($parent instanceof Fulfilment) {
            return $this->getNavigationRouteFulfilments($parent);
        } else {
            return [];
        }
    }

    protected function getNavigationRouteShops(Shop $shop): array
    {
        return [

            [
                "isAnchor" => true,
                "route"    => [
                    "name"       => "grp.org.shops.show.dashboard.comms.dashboard",
                    "parameters" => [$shop->organisation->slug, $shop->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-chart-network"],
                    "tooltip" => __("Tree view of the webpages"),
                ],
            ],
            [
                "label"    => __("All"),
                "route"    => [
                    "name"       => "grp.org.shops.show.dashboard.comms.outboxes.index",
                    "parameters" => [$shop->organisation->slug, $shop->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-inbox-out"],
                    "tooltip" => __("All"),
                ],
                'align'  => 'right'
            ],

        ];
    }

    protected function getNavigationRouteFulfilments(Fulfilment $fulfilment): array
    {
        return [

            [
                "isAnchor" => true,
                "label"    => __("Comms Dashboard"),
                "route"    => [
                    "name"       => "grp.org.fulfilments.show.operations.comms.dashboard",
                    "parameters" => [$fulfilment->organisation->slug, $fulfilment->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-chart-network"],
                    "tooltip" => __("Tree view of the webpages"),
                ],
            ],
            [
                "label"    => __("Post Rooms"),
                "route"    => [
                    "name"       => "grp.org.fulfilments.show.operations.comms.post-rooms.index",
                    "parameters" => [$fulfilment->organisation->slug, $fulfilment->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-inbox-out"],
                    "tooltip" => __("Post Rooms"),
                ],
            ],
            [
                "label"    => __("Outboxes"),
                "route"    => [
                    "name"       => "grp.org.fulfilments.show.operations.comms.outboxes",
                    "parameters" => [$fulfilment->organisation->slug, $fulfilment->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-inbox-out"],
                    "tooltip" => __("Outboxes"),
                ],
            ],

        ];
    }
}
