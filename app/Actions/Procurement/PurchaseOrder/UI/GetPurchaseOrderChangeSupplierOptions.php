<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-17 15:01
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\ChangePurchaseOrderSupplier;
use App\Actions\Procurement\PurchaseOrderTransaction\GetPurchaseOrderTransactionMoveSuppliers;
use App\Actions\Traits\Authorisations\WithPurchaseOrderAgentEditAuthorisation;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Lorisleiva\Actions\ActionRequest;

/**
 * What the Change supplier dialog needs: the order's lines with their agreed price, and the other suppliers of the agent
 * in the order's currency, each with the reference the order would take and its product for the SKO of every line.
 */
class GetPurchaseOrderChangeSupplierOptions extends OrgAction
{
    use WithPurchaseOrderAgentEditAuthorisation;

    public function handle(PurchaseOrder $purchaseOrder): array
    {
        $currency = $purchaseOrder->currency?->code;
        $lines    = ChangePurchaseOrderSupplier::linesToChange($purchaseOrder);

        $suppliers = collect(GetPurchaseOrderTransactionMoveSuppliers::make()->handle($purchaseOrder, $lines))
            ->reject(fn (array $supplier) => $supplier['is_current'] || $supplier['currency'] !== $currency)
            ->map(fn (array $supplier) => [
                'org_supplier_id' => $supplier['org_supplier_id'],
                'code'            => $supplier['code'],
                'name'            => $supplier['name'],
                'reference'       => ChangePurchaseOrderSupplier::referenceFor($purchaseOrder, $supplier['code']),
                'products'        => $supplier['products'],
                'products_count'  => $supplier['products_count'],
            ])
            ->values()
            ->all();

        return [
            'blocked_reason' => ChangePurchaseOrderSupplier::blockedReason($purchaseOrder),
            'reference'      => $purchaseOrder->reference,
            'slug'           => $purchaseOrder->slug,
            'supplier'       => [
                'code' => $purchaseOrder->parent_code,
                'name' => $purchaseOrder->parent_name,
            ],
            'currency'       => $currency,
            'lines'          => $lines->map(fn (PurchaseOrderTransaction $transaction) => [
                'id'               => $transaction->id,
                'code'             => $transaction->supplierProduct?->code ?? $transaction->orgStock?->code,
                'name'             => $transaction->supplierProduct?->name ?? $transaction->orgStock?->name,
                'has_sko'          => $transaction->org_stock_id !== null,
                'quantity_ordered' => (float) $transaction->quantity_ordered,
                'unit_cost'        => ChangePurchaseOrderSupplier::agreedUnitCost($transaction),
                'units_per_carton' => $transaction->supplierProduct?->units_per_carton !== null ? (float) $transaction->supplierProduct->units_per_carton : null,
            ])->values()->all(),
            'suppliers'      => $suppliers,
        ];
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): array
    {
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder);
    }
}
