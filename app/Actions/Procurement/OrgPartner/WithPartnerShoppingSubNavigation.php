<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 29 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use Illuminate\Support\Arr;

trait WithPartnerShoppingSubNavigation
{
    protected function getPartnerShoppingNavigation(OrgPartner $parent): array
    {
        $linesByState = PartnerShoppingListItem::whereNotSplitPiece(
            PartnerShoppingListItem::where('org_partner_id', $parent->id)->whereIn('state', ShoppingListItemStateEnum::onPartnerBuyerList()),
            [ShoppingListItemStateEnum::OPEN->value, ShoppingListItemStateEnum::ORDERED->value]
        )
            ->selectRaw('state, count(*) as total')
            ->groupBy('state')
            ->pluck('total', 'state');

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
                "label"    => __("Ongoing PO"),
                "route"    => [
                    "name"       => "grp.org.procurement.org_partners.show.shopping_list.index",
                    "parameters" => [$parent->organisation->slug, $parent->id],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-list"],
                    "tooltip" => __("Ongoing PO"),
                ],
                "number"   => (int) ($linesByState[ShoppingListItemStateEnum::DRAFT->value] ?? 0),
            ],
            [
                "label"    => __("Sent"),
                "route"    => [
                    "name"       => "grp.org.procurement.org_partners.show.shopping_list.sent",
                    "parameters" => [$parent->organisation->slug, $parent->id],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-paper-plane"],
                    "tooltip" => __('Sent to :partner', ['partner' => $parent->partner->name]),
                ],
                "number"   => (int) ($linesByState[ShoppingListItemStateEnum::OPEN->value] ?? 0),
            ],
        ];
    }
}
