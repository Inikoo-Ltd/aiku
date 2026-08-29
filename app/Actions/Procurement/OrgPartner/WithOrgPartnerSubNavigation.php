<?php

/*
 * author Arya Permana - Kirin
 * created on 23-10-2024-14h-12m
 * github: https://github.com/KirinZero0
 * copyright 2024
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Models\Procurement\OrgPartner;
use Illuminate\Support\Arr;

trait WithOrgPartnerSubNavigation
{
    protected function getOrgPartnerNavigation(OrgPartner $parent): array
    {
        return [
            [
                "label"    => $parent->partner->slug,
                "route"    => [
                    "name"       => "grp.org.procurement.org_partners.show",
                    "parameters" => [$parent->organisation->slug, $parent->id],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-users-class"],
                    "tooltip" => __("Org Partner"),
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
                    "icon"    => ["fal", "fa-shopping-basket"],
                    "tooltip" => __("Shopping List"),
                ],
                "number"   => $parent->stats->number_open_shopping_list_items,
            ],
            [
                "align"    => "right",
                "route"    => [
                    "name"       => "grp.org.procurement.org_partners.show.purchase-orders.index",
                    "parameters" => [$parent->organisation->slug, $parent->id],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-clipboard"],
                    "tooltip" => __("Purchase Orders"),
                ],
            ],
            [
                "label"    => __("Org Stocks"),
                "route"    => [
                    "name"       => "grp.org.procurement.org_partners.show.org-stocks.index",
                    "parameters" => [$parent->organisation->slug, $parent->id],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-box"],
                    "tooltip" => __("Org Stocks"),
                ],
                "number"   => $parent->partner->inventoryStats->number_current_org_stocks,
            ],
            [
                "label"    => __("Stock Deliveries"),
                "route"    => [
                    "name"       => "grp.org.procurement.org_partners.show.stock-deliveries.index",
                    "parameters" => [$parent->organisation->slug, $parent->id],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-truck-container"],
                    "tooltip" => __("Stock Deliveries"),
                ],
                "number"   => $parent->partner->inventoryStats->number_deliveries,
            ],
        ];
    }
}
