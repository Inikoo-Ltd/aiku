<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchProcurement
{
    use AsAction;
    use WithRawSearchResults;

    public function handle(string $query, array $options): array
    {
        $organisationId = Arr::get($options, 'organisation_id');

        $purchaseOrdersQuery = PurchaseOrder::search($query);
        if ($organisationId) {
            $purchaseOrdersQuery->where('organisation_id', $organisationId);
        }

        $stockDeliveriesQuery = StockDelivery::search($query);
        if ($organisationId) {
            $stockDeliveriesQuery->where('organisation_id', $organisationId);
        }

        $agentsQuery = Agent::search($query);
        if ($organisationId) {
            $agentsQuery->where('organisation_ids', $organisationId);
        }

        $suppliersQuery = Supplier::search($query);
        if ($organisationId) {
            $suppliersQuery->where('organisation_ids', $organisationId);
        }

        $supplierProductsQuery = SupplierProduct::search($query);
        if ($organisationId) {
            $supplierProductsQuery->where('organisation_ids', $organisationId);
        }

        $agentSupplierPurchaseOrdersQuery = AgentSupplierPurchaseOrder::search($query);
        if ($organisationId) {
            $agentSupplierPurchaseOrdersQuery->where('organisation_ids', $organisationId);
        }

        $mapReferenceState = static fn (array $document) => [
            'id'        => (int)$document['id'],
            'reference' => $document['reference'] ?? null,
            'state'     => $document['state'] ?? null,
        ];

        $mapCodeNameState = static fn (array $document) => [
            'id'    => (int)$document['id'],
            'code'  => $document['code'] ?? null,
            'name'  => $document['name'] ?? null,
            'state' => $document['state'] ?? null,
        ];

        return [
            'scope'   => 'procurement',
            'results' => [
                'purchase_orders'                 => array_map($mapReferenceState, $this->rawDocuments($purchaseOrdersQuery)),
                'stock_deliveries'                 => array_map($mapReferenceState, $this->rawDocuments($stockDeliveriesQuery)),
                'agents'                           => array_map($mapCodeNameState, $this->rawDocuments($agentsQuery)),
                'suppliers'                        => array_map($mapCodeNameState, $this->rawDocuments($suppliersQuery)),
                'supplier_products'                => array_map($mapCodeNameState, $this->rawDocuments($supplierProductsQuery)),
                'agent_supplier_purchase_orders'   => array_map($mapReferenceState, $this->rawDocuments($agentSupplierPurchaseOrdersQuery)),
            ],
        ];
    }


}
