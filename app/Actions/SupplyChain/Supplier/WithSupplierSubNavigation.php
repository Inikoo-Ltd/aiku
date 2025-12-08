<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\SupplyChain\Supplier;

use App\Models\SupplyChain\Supplier;

trait WithSupplierSubNavigation
{
    protected function getSupplierNavigation(Supplier $parent): array
    {
        return [
            [
                'isAnchor' => true,
                'label' => __($parent->slug),

                'route' => [
                    'name' => 'grp.supply-chain.suppliers.show',
                    'parameters' => [$parent->slug],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-person-dolly'],
                    'tooltip' => __('Org Supplier'),
                ],
            ],
            [
                'number' => $parent->stats->number_supplier_products,
                'label' => __('Products'),
                'route' => [
                    'name' => 'grp.supply-chain.suppliers.supplier_products.index',
                    'parameters' => [$parent->slug],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-box-usd'],
                    'tooltip' => __('Products'),
                ],
            ],
            // wait purchase order:
            // [
            //     "number"   => $parent->stats->number_purchase_orders,
            //     "label"    => __("Purchase Orders"),
            //     "route"     => [
            //         "name"       => "grp.org.procurement.org_suppliers.show.purchase_orders.index",
            //         "parameters" => [$parent->organisation->slug, $parent->slug],
            //     ],
            //     "leftIcon" => [
            //         "icon"    => ["fal", "fa-clipboard"],
            //         "tooltip" => __("Purchase Orders"),
            //     ],
            // ],
            // [
            //     "number"   => $parent->stats->number_stock_deliveries,
            //     "label"    => __("Stock Deliveries"),
            //     "route"     => [
            //         "name"       => "grp.org.procurement.org_suppliers.show.stock_deliveries.index",
            //         "parameters" => [$parent->organisation->slug, $parent->slug],
            //     ],
            //     "leftIcon" => [
            //         "icon"    => ["fal", "fa-truck-container"],
            //         "tooltip" => __("Stock Deliveries"),
            //     ],
            // ],

        ];
    }
}
