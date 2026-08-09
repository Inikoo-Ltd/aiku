<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 12:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\SupplyChain\AgentSupplierPurchaseOrder\StoreAgentSupplierPurchaseOrder;
use App\Actions\SupplyChain\AgentSupplierPurchaseOrder\UpdateAgentSupplierPurchaseOrder;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FetchAuroraAgentSupplierPurchaseOrders extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:agent_supplier_purchase_orders {organisations?*} {--s|source_id=} {--d|db_suffix=} {--N|only_new : Fetch only new} {--r|reset}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?AgentSupplierPurchaseOrder
    {
        $data = $organisationSource->fetchAgentSupplierPurchaseOrder($organisationSourceId);
        if (!$data || empty($data['agent_supplier_purchase_order'])) {
            return null;
        }

        $modelData = $data['agent_supplier_purchase_order'];

        $agentSupplierPurchaseOrder = AgentSupplierPurchaseOrder::withTrashed()->where('source_id', $modelData['source_id'])->first();

        if (!$agentSupplierPurchaseOrder) {
            /* Aurora holds double-submitted duplicates of the same agent buy (same PO+supplier,
               same reference); merge them onto the first-fetched row instead of tripping the
               unique (purchase_order_id, supplier_id) index. */
            $agentSupplierPurchaseOrder = AgentSupplierPurchaseOrder::where('purchase_order_id', $data['purchase_order']->id)
                ->where('supplier_id', $data['supplier']->id)
                ->first();
            if ($agentSupplierPurchaseOrder) {
                $sourceData = explode(':', $modelData['source_id']);
                DB::connection('aurora')->table('Agent Supplier Purchase Order Dimension')
                    ->where('Agent Supplier Purchase Order Key', $sourceData[1])
                    ->update(['aiku_id' => $agentSupplierPurchaseOrder->id]);
                $modelData = Arr::except($modelData, ['source_id', 'reference']);
            }
        }

        if ($agentSupplierPurchaseOrder) {
            try {
                $agentSupplierPurchaseOrder = UpdateAgentSupplierPurchaseOrder::make()->action(
                    agentSupplierPurchaseOrder: $agentSupplierPurchaseOrder,
                    modelData: $modelData,
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
                $this->recordChange($organisationSource, $agentSupplierPurchaseOrder->wasChanged());
            } catch (Exception $e) {
                $this->recordError($organisationSource, $e, $modelData, 'AgentSupplierPurchaseOrder', 'update');

                return null;
            }
        } else {
            try {
                $agentSupplierPurchaseOrder = StoreAgentSupplierPurchaseOrder::make()->action(
                    purchaseOrder: $data['purchase_order'],
                    supplier: $data['supplier'],
                    modelData: $modelData,
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
                $this->recordNew($organisationSource);

                $sourceData = explode(':', $agentSupplierPurchaseOrder->source_id);
                DB::connection('aurora')->table('Agent Supplier Purchase Order Dimension')
                    ->where('Agent Supplier Purchase Order Key', $sourceData[1])
                    ->update(['aiku_id' => $agentSupplierPurchaseOrder->id]);
            } catch (Exception $e) {
                $this->recordError($organisationSource, $e, $modelData, 'AgentSupplierPurchaseOrder', 'store');

                return null;
            }
        }

        return $agentSupplierPurchaseOrder;
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Agent Supplier Purchase Order Dimension')
            ->select('Agent Supplier Purchase Order Key as source_id');
        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        $query->orderBy('Agent Supplier Purchase Order Creation Date');

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Agent Supplier Purchase Order Dimension');
        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->count();
    }

    public function reset(): void
    {
        DB::connection('aurora')->table('Agent Supplier Purchase Order Dimension')->update(['aiku_id' => null]);
    }
}
