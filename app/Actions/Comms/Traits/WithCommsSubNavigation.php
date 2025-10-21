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
                "label"    => __("Newsletters"),
                "route"    => [
                    "name"       => "grp.org.shops.show.dashboard.comms.newsletter_outboxes.index",
                    "parameters" => [$shop->organisation->slug, $shop->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-newspaper"],
                    "tooltip" => __("Newsletters"),
                ],
            ],
            [
                "label"    => __("Marketing"),
                "route"    => [
                    "name"       => "grp.org.shops.show.dashboard.comms.marketing_outboxes.index",
                    "parameters" => [$shop->organisation->slug, $shop->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-bullhorn"],
                    "tooltip" => __("Marketing"),
                ],
            ],
            [
                "label"    => __("Marketing Notifications"),
                "route"    => [
                    "name"       => "grp.org.shops.show.dashboard.comms.marketing_notification_outboxes.index",
                    "parameters" => [$shop->organisation->slug, $shop->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-radio"],
                    "tooltip" => __("Marketing Notifications"),
                ],
            ],
            [
                "label"    => __("Customer Notifications"),
                "route"    => [
                    "name"       => "grp.org.shops.show.dashboard.comms.customer_notification_outboxes.index",
                    "parameters" => [$shop->organisation->slug, $shop->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-sort-alt"],
                    "tooltip" => __("Customer Notifications"),
                ],
            ],
            [
                "label"    => __("Cold Emails"),
                "route"    => [
                    "name"       => "grp.org.shops.show.dashboard.comms.cold_email_outboxes.index",
                    "parameters" => [$shop->organisation->slug, $shop->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-phone-volume"],
                    "tooltip" => __("Cold Emails"),
                ],
            ],
            [
                "label"    => __("User Notifications"),
                "route"    => [
                    "name"       => "grp.org.shops.show.dashboard.comms.user_notification_outboxes.index",
                    "parameters" => [$shop->organisation->slug, $shop->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-bell"],
                    "tooltip" => __("User Notifications"),
                ],
            ],
            [
                "label"    => __("Push"),
                "route"    => [
                    "name"       => "grp.org.shops.show.dashboard.comms.push_outboxes.index",
                    "parameters" => [$shop->organisation->slug, $shop->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-project-diagram"],
                    "tooltip" => __("Push"),
                ],
            ],
            [
                "label"    => __("Test"),
                "route"    => [
                    "name"       => "grp.org.shops.show.dashboard.comms.test_outboxes.index",
                    "parameters" => [$shop->organisation->slug, $shop->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-vial"],
                    "tooltip" => __("Test"),
                ],
                'align'  => 'right'
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
