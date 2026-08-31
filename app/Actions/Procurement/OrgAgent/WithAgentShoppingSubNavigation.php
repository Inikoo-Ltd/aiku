<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent;

use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\ShoppingListItem;

trait WithAgentShoppingSubNavigation
{
    protected function getAgentShoppingNavigation(OrgAgent $parent): array
    {
        return [
            [
                "label"    => __("Shopping"),
                "route"    => [
                    "name"       => "grp.org.procurement.org_agents.show.shopping.dashboard",
                    "parameters" => [$parent->organisation->slug, $parent->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-shopping-basket"],
                    "tooltip" => __("Shopping"),
                ],
                "isAnchor" => true,
            ],
            [
                "label"    => __("Suppliers"),
                "route"    => [
                    "name"       => "grp.org.procurement.org_agents.show.suppliers.index",
                    "parameters" => [$parent->organisation->slug, $parent->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-person-dolly"],
                    "tooltip" => __("Suppliers"),
                ],
                "number"   => $parent->stats->number_org_suppliers,
            ],
            [
                "label"    => __("Shopping List"),
                "route"    => [
                    "name"       => "grp.org.procurement.shopping_list.index",
                    "parameters" => [$parent->organisation->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-list"],
                    "tooltip" => __("Shopping List"),
                ],
                "number"   => ShoppingListItem::where('organisation_id', $parent->organisation_id)
                    ->where('agent_id', $parent->agent_id)
                    ->where('state', ShoppingListItemStateEnum::OPEN)
                    ->count(),
            ],
        ];
    }
}
