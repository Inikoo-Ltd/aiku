<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct\UI;

use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Models\Goods\Stock;
use App\Models\Goods\TradeUnit;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;

trait WithSupplierProductShowcase
{
    protected function getSupplierProductShowcase(SupplierProduct $supplierProduct, bool $withSupplyChainLink = false): array
    {
        $supplierProduct->loadMissing(['currency', 'supplier', 'agent', 'tradeUnits', 'stocks']);

        return [
            'product'     => $this->getSupplierProductDetails($supplierProduct, $withSupplyChainLink),
            'costs'       => $this->getSupplierProductCosts($supplierProduct),
            'packaging'   => $this->getSupplierProductPackaging($supplierProduct),
            'trade_units' => $this->getSupplierProductTradeUnits($supplierProduct),
            'stocks'      => $this->getSupplierProductStocks($supplierProduct),
        ];
    }

    private function getSupplierProductDetails(SupplierProduct $supplierProduct, bool $withSupplyChainLink): array
    {
        return [
            'code'         => $supplierProduct->code,
            'name'         => $supplierProduct->name,
            'description'  => $supplierProduct->description,
            'image'        => $supplierProduct->tradeUnits->first()?->imageSources(),
            'state'        => [
                'label' => SupplierProductStateEnum::labels()[$supplierProduct->state->value],
                'icon'  => SupplierProductStateEnum::stateIcon()[$supplierProduct->state->value],
            ],
            'is_available' => $supplierProduct->is_available,
            'composition'  => $supplierProduct->trade_unit_composition?->value,
            'route'        => $withSupplyChainLink && $supplierProduct->supplier ? [
                'name'       => 'grp.supply-chain.suppliers.supplier_products.show',
                'parameters' => [
                    'supplier'        => $supplierProduct->supplier->slug,
                    'supplierProduct' => $supplierProduct->slug,
                ],
            ] : null,
        ];
    }

    private function getSupplierProductCosts(SupplierProduct $supplierProduct): array
    {
        $unitCost      = (float) $supplierProduct->cost;
        $extraCosts    = (float) $supplierProduct->extra_costs;
        $deliveredCost = $unitCost * (1 + $extraCosts);

        return [
            'currency_code'          => $supplierProduct->currency?->code,
            'unit_cost'              => $unitCost,
            'extra_costs_percentage' => $extraCosts * 100,
            'delivered_unit_cost'    => $deliveredCost,
            'pack_cost'              => $supplierProduct->units_per_pack ? $deliveredCost * $supplierProduct->units_per_pack : null,
            'carton_cost'            => $supplierProduct->units_per_carton ? $deliveredCost * $supplierProduct->units_per_carton : null,
        ];
    }

    private function getSupplierProductPackaging(SupplierProduct $supplierProduct): array
    {
        return [
            'units_per_pack'   => $supplierProduct->units_per_pack,
            'units_per_carton' => $supplierProduct->units_per_carton,
            'cbm'              => $supplierProduct->cbm ? (float) $supplierProduct->cbm : null,
        ];
    }

    private function getSupplierProductTradeUnits(SupplierProduct $supplierProduct): array
    {
        return $supplierProduct->tradeUnits->map(function (TradeUnit $tradeUnit) {
            return [
                'slug'  => $tradeUnit->slug,
                'code'  => $tradeUnit->code,
                'name'  => $tradeUnit->name,
                'unit'  => $tradeUnit->type,
                'units' => trimDecimalZeros($tradeUnit->pivot->quantity),
                'image' => $tradeUnit->imageSources(),
                'route' => [
                    'name'       => 'grp.trade_units.units.show',
                    'parameters' => ['tradeUnit' => $tradeUnit->slug],
                ],
            ];
        })->all();
    }

    private function getSupplierProductStocks(SupplierProduct $supplierProduct): array
    {
        return $supplierProduct->stocks->map(function (Stock $stock) {
            return [
                'slug'  => $stock->slug,
                'code'  => $stock->code,
                'name'  => $stock->name,
                'route' => [
                    'name'       => 'grp.goods.stocks.show',
                    'parameters' => ['stock' => $stock->slug],
                ],
            ];
        })->all();
    }

    protected function getSupplierParty(?Supplier $supplier): ?array
    {
        if (!$supplier) {
            return null;
        }

        return [
            'label' => __('Supplier'),
            'icon'  => 'fal fa-person-dolly',
            'name'  => $supplier->name,
            'code'  => $supplier->code,
            'image' => $supplier->imageSources(),
            'route' => [
                'name'       => 'grp.supply-chain.suppliers.show',
                'parameters' => ['supplier' => $supplier->slug],
            ],
        ];
    }

    protected function getAgentParty(?Agent $agent): ?array
    {
        if (!$agent) {
            return null;
        }

        return [
            'label' => __('Agent'),
            'icon'  => 'fal fa-people-arrows',
            'name'  => $agent->organisation->name,
            'code'  => $agent->organisation->code,
            'image' => $agent->imageSources(),
            'route' => [
                'name'       => 'grp.supply-chain.agents.show',
                'parameters' => ['agent' => $agent->slug],
            ],
        ];
    }

    protected function getOrgSupplierParty(?OrgSupplier $orgSupplier): ?array
    {
        if (!$orgSupplier || !$orgSupplier->supplier) {
            return null;
        }

        return [
            'label' => __('Supplier'),
            'icon'  => 'fal fa-person-dolly',
            'name'  => $orgSupplier->supplier->name,
            'code'  => $orgSupplier->supplier->code,
            'image' => $orgSupplier->supplier->imageSources(),
            'route' => [
                'name'       => 'grp.org.procurement.org_suppliers.show',
                'parameters' => [
                    'organisation' => $orgSupplier->organisation->slug,
                    'orgSupplier'  => $orgSupplier->slug,
                ],
            ],
        ];
    }

    protected function getOrgAgentParty(?OrgAgent $orgAgent): ?array
    {
        if (!$orgAgent || !$orgAgent->agent) {
            return null;
        }

        return [
            'label' => __('Agent'),
            'icon'  => 'fal fa-people-arrows',
            'name'  => $orgAgent->agent->organisation->name,
            'code'  => $orgAgent->agent->organisation->code,
            'image' => $orgAgent->agent->imageSources(),
            'route' => [
                'name'       => 'grp.org.procurement.org_agents.show',
                'parameters' => [
                    'organisation' => $orgAgent->organisation->slug,
                    'orgAgent'     => $orgAgent->slug,
                ],
            ],
        ];
    }

    protected function getProcurementStatsBoxes(?object $stats): array
    {
        if (!$stats) {
            return [];
        }

        return [
            [
                'label'       => __('Purchase Orders'),
                'count'       => $stats->number_purchase_orders,
                'description' => __('open').': '.$stats->number_open_purchase_orders,
            ],
            [
                'label' => __('Deliveries'),
                'count' => $stats->number_stock_deliveries,
            ],
            [
                'label' => __('Ordered Items'),
                'count' => $stats->number_purchase_order_transactions,
            ],
            [
                'label' => __('Delivered Items'),
                'count' => $stats->number_stock_delivery_items,
            ],
        ];
    }
}
