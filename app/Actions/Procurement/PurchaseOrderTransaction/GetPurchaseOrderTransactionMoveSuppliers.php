<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithPurchaseOrderAgentEditAuthorisation;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Lorisleiva\Actions\ActionRequest;

/**
 * The other suppliers of the agent a line can move to, each with its product for the same SKO when it has one.
 */
class GetPurchaseOrderTransactionMoveSuppliers extends OrgAction
{
    use WithPurchaseOrderAgentEditAuthorisation;

    public function handle(PurchaseOrderTransaction $purchaseOrderTransaction): array
    {
        $purchaseOrder = $purchaseOrderTransaction->purchaseOrder;

        return OrgSupplier::where('organisation_id', $purchaseOrder->organisation_id)
            ->where('status', true)
            ->whereKeyNot($purchaseOrder->parent_id)
            ->whereHas('orgAgent', fn ($query) => $query->where('agent_id', $purchaseOrder->agent_id))
            ->with('supplier.currency')
            ->get()
            ->sortBy(fn (OrgSupplier $orgSupplier) => $orgSupplier->supplier->code)
            ->map(function (OrgSupplier $orgSupplier) use ($purchaseOrderTransaction) {
                $supplierProduct = MovePurchaseOrderTransactionToSupplier::orgSupplierProductOfStock($orgSupplier, $purchaseOrderTransaction->org_stock_id)?->supplierProduct;

                return [
                    'org_supplier_id' => $orgSupplier->id,
                    'code'            => $orgSupplier->supplier->code,
                    'name'            => $orgSupplier->supplier->name,
                    'currency'        => $orgSupplier->supplier->currency?->code,
                    'product'         => $supplierProduct ? [
                        'code'             => $supplierProduct->code,
                        'cost'             => (float) $supplierProduct->cost,
                        'units_per_carton' => (float) $supplierProduct->units_per_carton,
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }

    public function asController(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $purchaseOrderTransaction, ActionRequest $request): array
    {
        abort_unless($purchaseOrderTransaction->purchase_order_id === $purchaseOrder->id, 404);
        $this->initialisation($purchaseOrderTransaction->organisation, $request);

        return $this->handle($purchaseOrderTransaction);
    }
}
