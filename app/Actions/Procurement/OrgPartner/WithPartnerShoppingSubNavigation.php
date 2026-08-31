<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 29 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Models\Procurement\OrgPartner;
use Illuminate\Support\Arr;

trait WithPartnerShoppingSubNavigation
{
    protected function getPartnerShoppingNavigation(OrgPartner $parent): array
    {
        return [
            [
                "label"    => __("Shopping"),
                "route"    => [
                    "name"       => "grp.org.procurement.org_partners.show.shopping.dashboard",
                    "parameters" => [$parent->organisation->slug, $parent->id],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-shopping-basket"],
                    "tooltip" => __("Shopping"),
                ],
                "isAnchor" => true,
            ],
            ...(Arr::get($parent->partner->settings, 'procurement.shop_id') ? [
                [
                    "label"    => __("Browse"),
                    "route"    => [
                        "name"       => "grp.org.procurement.org_partners.show.browse.index",
                        "parameters" => [$parent->organisation->slug, $parent->id],
                    ],
                    "leftIcon" => [
                        "icon"    => ["fal", "fa-store"],
                        "tooltip" => __("Browse"),
                    ],
                ],
            ] : []),
            [
                "label"    => __("Shopping List"),
                "route"    => [
                    "name"       => "grp.org.procurement.org_partners.show.shopping_list.index",
                    "parameters" => [$parent->organisation->slug, $parent->id],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-list"],
                    "tooltip" => __("Shopping List"),
                ],
                "number"   => $parent->stats->number_open_shopping_list_items,
            ],
        ];
    }
}
