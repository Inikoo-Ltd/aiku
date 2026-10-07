<?php

/*
 * author Arya Permana - Kirin
 * created on 23-10-2024-13h-18m
 * github: https://github.com/KirinZero0
 * copyright 2024
 */

namespace App\Actions\Procurement\OrgAgent;

use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Procurement\OrgAgent;

trait WithOrgAgentSubNavigation
{
    protected function getOrgAgentNavigation(OrgAgent $parent): array
    {
        return [
            [
                "label"    => $parent->slug,
                "route"    => [
                    "name"       => "grp.org.procurement.org_agents.show",
                    "parameters" => [$this->organisation->slug, $parent->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-people-arrows"],
                    "tooltip" => __("Org Agent"),
                ],
                "isAnchor" => true,
            ],
            ...($this->organisation->type === OrganisationTypeEnum::AGENT ? [] : [
                [
                    "label"    => __("Shopping"),
                    "route"    => [
                        "name"       => "grp.org.procurement.org_agents.show.shopping.dashboard",
                        "parameters" => [$this->organisation->slug, $parent->slug],
                    ],
                    "leftIcon" => [
                        "icon"    => ["fal", "fa-shopping-basket"],
                        "tooltip" => __("Shopping"),
                    ],
                ],
            ]),
            [
                "label"    => __("Suppliers"),
                "route"    => [
                    "name"       => "grp.org.procurement.org_agents.show.suppliers.index",
                    "parameters" => [$this->organisation->slug, $parent->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-person-dolly"],
                    "tooltip" => __("Suppliers"),
                ],
                "number"   => $parent->stats->number_org_suppliers,
            ],
            [
                "label"    => __("Products"),
                "route"    => [
                    "name"       => "grp.org.procurement.org_agents.show.supplier_products.index",
                    "parameters" => [$this->organisation->slug, $parent->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-box-usd"],
                    "tooltip" => __("Products"),
                ],
                "number"   => $parent->stats->number_org_supplier_products,
            ],
            [
                "label"    => __("Purchase Orders"),
                "route"    => [
                    "name"       => "grp.org.procurement.org_agents.show.purchase-orders.index",
                    "parameters" => [$this->organisation->slug, $parent->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-clipboard"],
                    "tooltip" => __("Purchase Orders"),
                ],
                "number"   => $parent->stats->number_purchase_orders,
            ],
            [
                "label"    => __("Supplier Purchase Orders"),
                "route"    => [
                    "name"       => "grp.org.procurement.org_agents.show.agent_supplier_purchase_orders.index",
                    "parameters" => [$this->organisation->slug, $parent->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-clipboard-list"],
                    "tooltip" => __("Supplier Purchase Orders"),
                ],
                "number"   => $parent->stats->number_agent_supplier_purchase_orders,
            ],
            [
                "label"    => __("Stock Deliveries"),
                "route"    => [
                    "name"       => "grp.org.procurement.org_agents.show.stock-deliveries.index",
                    "parameters" => [$this->organisation->slug, $parent->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-truck-container"],
                    "tooltip" => __("Stock Deliveries"),
                ],
                "number"   => $parent->stats->number_stock_deliveries,
            ],
        ];
    }
}
