<?php
/*
 * author Arya Permana - Kirin
 * created on 23-10-2024-14h-12m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Procurement\OrgPartner;

use App\Models\Procurement\OrgPartner;

trait WithOrgPartnerSubNavigation
{
    protected function getOrgPartnerNavigation(OrgPartner $parent): array
    {
        return [
            [
                "isAnchor" => true,
                "label"    => __($parent->partner->slug),

                "href"     => [
                    "name"       => "grp.org.procurement.org_partners.show",
                    "parameters" => [$parent->organisation->slug, $parent->id],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-users-class"],
                    "tooltip" => __("Org Partner"),
                ],
            ],
            [
                "number"   => $parent->partner->procurementStats->number_purchase_orders,
                "label"    => __("Purchase Orders"),
                "href"     => [
                    "name"       => "grp.org.procurement.org_partners.show.purchase-orders.index",
                    "parameters" => [$parent->organisation->slug, $parent->id],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-clipboard"],
                    "tooltip" => __("Purchase Orders"),
                ],
            ],
            [
                "number"   => $parent->partner->inventoryStats->number_current_org_stocks,
                "label"    => __("Org Stocks"),
                "href"     => [
                    "name"       => "grp.org.procurement.org_partners.show.org-stocks.index",
                    "parameters" => [$parent->organisation->slug, $parent->id],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-clipboard"],
                    "tooltip" => __("Org Stocks"),
                ],
            ],

        ];
    }
}
