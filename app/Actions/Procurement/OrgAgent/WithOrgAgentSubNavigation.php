<?php

/*
 * author Arya Permana - Kirin
 * created on 23-10-2024-13h-18m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Procurement\OrgAgent;

use App\Models\Procurement\OrgAgent;

trait WithOrgAgentSubNavigation
{
    protected function getOrgAgentNavigation(OrgAgent $parent): array
    {
        return [
            [
                'isAnchor' => true,
                'label' => __($parent->slug),

                'route' => [
                    'name' => 'grp.org.procurement.org_agents.show',
                    'parameters' => [$parent->organisation->slug, $parent->slug],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-people-arrows'],
                    'tooltip' => __('Org Agent'),
                ],
            ],
            [
                'number' => $parent->stats->number_org_suppliers,
                'label' => __('Suppliers'),
                'route' => [
                    'name' => 'grp.org.procurement.org_agents.show.suppliers.index',
                    'parameters' => [$parent->organisation->slug, $parent->slug],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-person-dolly'],
                    'tooltip' => __('Suppliers'),
                ],
            ],
            [
                'number' => $parent->stats->number_org_supplier_products,
                'label' => __('Products'),
                'route' => [
                    'name' => 'grp.org.procurement.org_agents.show.supplier_products.index',
                    'parameters' => [$parent->organisation->slug, $parent->slug],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-box-usd'],
                    'tooltip' => __('Products'),
                ],
            ],
            [
                'number' => $parent->stats->number_purchase_orders,
                'label' => __('Purchase Orders'),
                'route' => [
                    'name' => 'grp.org.procurement.org_agents.show.purchase-orders.index',
                    'parameters' => [$parent->organisation->slug, $parent->slug],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-clipboard'],
                    'tooltip' => __('Purchase Orders'),
                ],
            ],
            // [
            //     "number"   => $parent->agent->organisation->inventoryStats->number_org_stocks,
            //     "label"    => __("Org Stocks"),
            //     "route"     => [
            //         "name"       => "grp.org.procurement.org_agents.show.org-stocks.index",
            //         "parameters" => [$parent->organisation->slug, $parent->slug],
            //     ],
            //     "leftIcon" => [
            //         "icon"    => ["fal", "fa-box"],
            //         "tooltip" => __("Org Stocks"),
            //     ],
            // ],
            [
                'number' => $parent->stats->number_stock_deliveries,
                'label' => __('Stock Deliveries'),
                'route' => [
                    'name' => 'grp.org.procurement.org_agents.show.stock-deliveries.index',
                    'parameters' => [$parent->organisation->slug, $parent->slug],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-truck-container'],
                    'tooltip' => __('Stock Deliveries'),
                ],
            ],

        ];
    }
}
