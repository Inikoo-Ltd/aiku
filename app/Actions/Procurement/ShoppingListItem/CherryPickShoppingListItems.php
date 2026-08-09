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
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Inventory\OrgStockHasOrgSupplierProduct;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\ShoppingListItem;
use App\Models\SupplyChain\Agent;
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

            $orgStock = $pivot?->orgStock;

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
                ?? StorePurchaseOrder::make()->action($orgAgent, []);

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
            'purchase_orders' => array_values($purchaseOrders),
            'picked'          => $picked,
            'skipped'         => $skipped,
        ];
    }

    public function asController(Agent $agent, ActionRequest $request): array
    {
        $this->agent = $agent;
        $this->initialisation($agent->organisation, $request);

        return $this->handle($agent, $request->input('lines', []));
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): array
    {
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);

        $this->agent = $agent;
        $this->initialisation($organisation, $request);

        return $this->handle($agent, $request->input('lines', []));
    }

    public function action(Agent $agent, array $lines): array
    {
        $this->asAction = true;
        $this->agent = $agent;
        $this->initialisation($agent->organisation, []);

        return $this->handle($agent, $lines);
    }
}
