<?php

/*
 * author Arya Permana - Kirin
 * created on 12-11-2024-14h-35m
 * github: https://github.com/KirinZero0
 * copyright 2024
 */

namespace App\Actions\Procurement\OrgSupplier;

use App\Models\Procurement\OrgSupplier;

trait WithOrgSupplierSubNavigation
{
    protected function getOrgSupplierNavigation(OrgSupplier $parent): array
    {
        $routeParameters = [$parent->organisation->slug, $parent->slug];

        return [
            [
                'label'    => $parent->slug,
                'route'    => [
                    'name'       => 'grp.org.procurement.org_suppliers.show',
                    'parameters' => $routeParameters,
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-person-dolly'],
                    'tooltip' => __('Org Supplier'),
                ],
                'isAnchor' => true,
            ],
            [
                'label'    => __('Products'),
                'route'    => [
                    'name'       => 'grp.org.procurement.org_suppliers.show.supplier_products.index',
                    'parameters' => $routeParameters,
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-box-usd'],
                    'tooltip' => __('Products'),
                ],
                'number'   => $parent->stats->number_org_supplier_products,
            ],
            [
                'label'    => __('Purchase Orders'),
                'route'    => [
                    'name'       => 'grp.org.procurement.org_suppliers.show.purchase_orders.index',
                    'parameters' => $routeParameters,
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-clipboard'],
                    'tooltip' => __('Purchase Orders'),
                ],
                'number'   => $parent->stats->number_purchase_orders,
            ],
            [
                'label'    => __('Stock Deliveries'),
                'route'    => [
                    'name'       => 'grp.org.procurement.org_suppliers.show.stock_deliveries.index',
                    'parameters' => $routeParameters,
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-truck-container'],
                    'tooltip' => __('Stock Deliveries'),
                ],
                'number'   => $parent->stats->number_stock_deliveries,
            ],
        ];
    }
}
