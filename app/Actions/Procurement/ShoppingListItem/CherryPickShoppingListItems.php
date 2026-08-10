<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\ShoppingListItem;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\SupplyChain\AgentSupplierPurchaseOrder\StoreAgentSupplierPurchaseOrder;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Enums\SupplyChain\AgentSupplierPurchaseOrders\AgentSupplierPurchaseOrderStateEnum;
use App\Models\Inventory\OrgStockHasOrgSupplierProduct;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Models\Goods\Stock;
use App\Models\Inventory\OrgStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\ShoppingListItem;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class CherryPickShoppingListItems extends OrgAction
{
    use WithAgentOrganisation;

    private Agent $agent;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($request->user()->authTo('supply-chain.edit')) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->agent->organisation_id}.edit");
    }

    public function handle(Agent $agent, array $lines): array
    {
        $supplierIds = $agent->suppliers()->pluck('id');

        $ids = collect($lines)->pluck('id');
        $items = ShoppingListItem::query()
            ->whereIn('id', $ids)
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->whereIn('supplier_id', $supplierIds)
            ->get()
            ->keyBy('id');

        $purchaseOrders = [];
        $agentSupplierPurchaseOrders = [];
        $skipped = [];
        $picked = 0;

        foreach ($lines as $line) {
            /** @var ShoppingListItem|null $item */
            $item = $items->get($line['id']);
            if (!$item) {
                $skipped[] = ['id' => $line['id'], 'reason' => 'not found, not open, or not under this agent'];
                continue;
            }

            $pivot = OrgStockHasOrgSupplierProduct::where('org_supplier_product_id', $item->org_supplier_product_id)
                ->where('status', true)
                ->orderBy('local_priority')
                ->first();

            $orgStock = $pivot?->orgStock ?? $this->resolveOrCreateOrgStock($item);

            if (!$orgStock) {
                $skipped[] = ['id' => $item->id, 'reason' => 'org stock could not be resolved'];
                continue;
            }

            $orgAgent = OrgAgent::where('organisation_id', $item->organisation_id)
                ->where('agent_id', $agent->id)
                ->first();

            if (!$orgAgent) {
                $skipped[] = ['id' => $item->id, 'reason' => 'no org-agent relationship for this organisation'];
                continue;
            }

            $purchaseOrder = $purchaseOrders[$orgAgent->id]
                ?? $orgAgent->purchaseOrders()->where('state', PurchaseOrderStateEnum::IN_PROCESS)->first()
                ?? StorePurchaseOrder::make()->action($orgAgent, [])->fresh();

            $purchaseOrders[$orgAgent->id] = $purchaseOrder;

            $quantityRequested = (float) ($line['quantity_units'] ?? $item->quantity_units);
            $quantityPicked    = min($quantityRequested, (float) $item->quantity_units);
            $remainder         = (float) $item->quantity_units - $quantityPicked;

            $historicSupplierProduct = $item->supplierProduct->historicSupplierProduct;

            $purchaseOrderTransaction = StorePurchaseOrderTransaction::make()->action(
                $purchaseOrder,
                $historicSupplierProduct,
                $orgStock,
                ['quantity_ordered' => $quantityPicked]
            );

            $aspoKey = $purchaseOrder->id.'-'.$item->supplier_id;

            $agentSupplierPurchaseOrder = $agentSupplierPurchaseOrders[$aspoKey]
                ?? AgentSupplierPurchaseOrder::where('purchase_order_id', $purchaseOrder->id)
                    ->where('supplier_id', $item->supplier_id)
                    ->where('state', AgentSupplierPurchaseOrderStateEnum::IN_PROCESS->value)
                    ->first()
                ?? StoreAgentSupplierPurchaseOrder::make()->action(
                    purchaseOrder: $purchaseOrder,
                    supplier: $item->supplier,
                    modelData: []
                );

            $agentSupplierPurchaseOrders[$aspoKey] = $agentSupplierPurchaseOrder;

            $purchaseOrderTransaction->update(['agent_supplier_purchase_order_id' => $agentSupplierPurchaseOrder->id]);

            if ($remainder > 0) {
                ShoppingListItem::create([
                    ...$item->only([
                        'group_id',
                        'organisation_id',
                        'org_supplier_product_id',
                        'supplier_product_id',
                        'supplier_id',
                        'agent_id',
                        'units_per_pack_snapshot',
                        'units_per_carton_snapshot',
                        'priority',
                        'needed_by',
                        'notes',
                        'added_by_user_id',
                    ]),
                    'parent_id'      => $item->id,
                    'quantity_units' => $remainder,
                    'state'          => ShoppingListItemStateEnum::OPEN,
                    'created_at'     => $item->created_at,
                ]);
            }

            $item->update([
                'quantity_units'                 => $quantityPicked,
                'state'                           => ShoppingListItemStateEnum::ORDERED,
                'purchase_order_transaction_id'  => $purchaseOrderTransaction->id,
            ]);

            $picked++;
        }

        return [
            'purchase_orders'                => array_values($purchaseOrders),
            'agent_supplier_purchase_orders' => array_values($agentSupplierPurchaseOrders),
            'picked'                         => $picked,
            'skipped'                        => $skipped,
        ];
    }

    public function asController(Agent $agent, ActionRequest $request): array
    {
        $this->agent = $agent;
        $this->initialisation($agent->organisation, $request);

        return $this->handle($agent, $request->input('lines', []));
    }

    private function resolveOrCreateOrgStock(ShoppingListItem $item): ?OrgStock
    {
        $tradeUnitIds = DB::table('model_has_trade_units')
            ->where('model_type', 'SupplierProduct')
            ->where('model_id', $item->supplier_product_id)
            ->pluck('trade_unit_id');

        if ($tradeUnitIds->isEmpty()) {
            return null;
        }

        $orgStock = OrgStock::where('organisation_id', $item->organisation_id)
            ->whereIn('id', DB::table('model_has_trade_units')
                ->where('model_type', 'OrgStock')
                ->whereIn('trade_unit_id', $tradeUnitIds)
                ->pluck('model_id'))
            ->first();

        if ($orgStock) {
            return $orgStock;
        }

        $stock = Stock::whereIn('id', DB::table('model_has_trade_units')
            ->where('model_type', 'Stock')
            ->whereIn('trade_unit_id', $tradeUnitIds)
            ->pluck('model_id'))
            ->first();

        if (!$stock) {
            return null;
        }

        return StoreOrgStock::make()->action($item->organisation, $stock);
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): RedirectResponse
    {
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);

        $this->agent = $agent;
        $this->initialisation($organisation, $request);

        $result = $this->handle($agent, $request->input('lines', []));

        return Redirect::back()->with('notification', [
            'status'  => $result['skipped'] === [] ? 'success' : 'warning',
            'title'   => __('Purchase orders'),
            'message' => __(':picked lines picked, :skipped skipped', ['picked' => $result['picked'], 'skipped' => count($result['skipped'])]),
        ]);
    }

    public function action(Agent $agent, array $lines): array
    {
        $this->asAction = true;
        $this->agent = $agent;
        $this->initialisation($agent->organisation, []);

        return $this->handle($agent, $lines);
    }
}
