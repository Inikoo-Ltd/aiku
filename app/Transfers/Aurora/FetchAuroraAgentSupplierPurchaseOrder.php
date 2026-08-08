<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 12:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\SupplyChain\AgentSupplierPurchaseOrders\AgentSupplierPurchaseOrderDeliveryStateEnum;
use App\Enums\SupplyChain\AgentSupplierPurchaseOrders\AgentSupplierPurchaseOrderStateEnum;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class FetchAuroraAgentSupplierPurchaseOrder extends FetchAurora
{
    protected function parseModel(): void
    {
        $supplier = $this->parseSupplier(
            $this->organisation->id.':'.$this->auroraModelData->{'Agent Supplier Purchase Order Supplier Key'}
        );
        if (!$supplier) {
            print "Error No supplier found for agent supplier purchase order ".$this->auroraModelData->{'Agent Supplier Purchase Order Key'}."\n";

            return;
        }

        $purchaseOrder = PurchaseOrder::withTrashed()
            ->where('source_id', $this->organisation->id.':'.$this->auroraModelData->{'Agent Supplier Purchase Order Purchase Order Key'})
            ->first();
        if (!$purchaseOrder) {
            print "Error No purchase order found for agent supplier purchase order ".$this->auroraModelData->{'Agent Supplier Purchase Order Key'}." (fetch purchase orders first)\n";

            return;
        }

        $this->parsedData['supplier']       = $supplier;
        $this->parsedData['purchase_order'] = $purchaseOrder;

        $createdAt   = $this->parseDatetime($this->auroraModelData->{'Agent Supplier Purchase Order Creation Date'});
        $confirmedAt = $this->parseDatetime($this->auroraModelData->{'Agent Supplier Purchase Order Confirm Date'});
        $cancelledAt = $this->parseDatetime($this->auroraModelData->{'Agent Supplier Purchase Order Cancelled Date'});

        $state = match ($this->auroraModelData->{'Agent Supplier Purchase Order State'}) {
            'InProcess' => AgentSupplierPurchaseOrderStateEnum::IN_PROCESS,
            'Cancelled' => AgentSupplierPurchaseOrderStateEnum::CANCELLED,
            default => AgentSupplierPurchaseOrderStateEnum::CONFIRMED,
        };

        $deliveryState = match ($this->auroraModelData->{'Agent Supplier Purchase Order State'}) {
            'Confirmed' => AgentSupplierPurchaseOrderDeliveryStateEnum::CONFIRMED,
            'InWarehouse' => AgentSupplierPurchaseOrderDeliveryStateEnum::RECEIVED,
            'InDelivery' => AgentSupplierPurchaseOrderDeliveryStateEnum::DISPATCHED,
            'Cancelled' => AgentSupplierPurchaseOrderDeliveryStateEnum::CANCELLED,
            default => AgentSupplierPurchaseOrderDeliveryStateEnum::IN_PROCESS,
        };

        $date = $cancelledAt ?? $confirmedAt ?? $createdAt ?? now();

        $this->parsedData['agent_supplier_purchase_order'] = [
            'reference'       => (string)($this->auroraModelData->{'Agent Supplier Purchase Order Public ID'} ?: $this->auroraModelData->{'Agent Supplier Purchase Order Key'}),
            'state'           => $state,
            'delivery_state'  => $deliveryState,
            'date'            => $date,
            'confirmed_at'    => $confirmedAt,
            'cancelled_at'    => $cancelledAt,
            'cost_total'      => $this->auroraModelData->{'Agent Supplier Purchase Order Amount'} ?? 0,
            'currency_id'     => $this->parseCurrencyID($this->auroraModelData->{'Agent Supplier Purchase Order Currency Code'}),
            'source_id'       => $this->organisation->id.':'.$this->auroraModelData->{'Agent Supplier Purchase Order Key'},
            'created_at'      => $createdAt,
            'fetched_at'      => now(),
            'last_fetched_at' => now(),
        ];
    }

    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Agent Supplier Purchase Order Dimension')
            ->where('Agent Supplier Purchase Order Key', $id)
            ->first();
    }
}
