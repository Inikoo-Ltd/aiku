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
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

/**
 * The other suppliers of the agent the lines can move to, each with its product for the SKO of every line when it has
 * one, the order the lines would land on and its open orders that can take them. A handful of queries for all the
 * suppliers: an agent can have over a hundred.
 */
class GetPurchaseOrderTransactionMoveSuppliers extends OrgAction
{
    use WithPurchaseOrderAgentEditAuthorisation;

    /**
     * @param Collection<int, PurchaseOrderTransaction> $purchaseOrderTransactions
     */
    public function handle(PurchaseOrder $purchaseOrder, Collection $purchaseOrderTransactions): array
    {
        $move         = MovePurchaseOrderTransactionToSupplier::make();
        $orgSuppliers = OrgSupplier::where('organisation_id', $purchaseOrder->organisation_id)
            ->where('status', true)
            ->whereKeyNot($purchaseOrder->parent_id)
            ->whereHas('orgAgent', fn ($query) => $query->where('agent_id', $purchaseOrder->agent_id))
            ->with('supplier.currency')
            ->get();

        $orgSupplierIds       = $orgSuppliers->pluck('id')->all();
        $orgStockIds          = $purchaseOrderTransactions->pluck('org_stock_id')->filter()->unique()->values()->all();
        $productsBySupplier   = MovePurchaseOrderTransactionToSupplier::orgSupplierProductsOfStocks($orgSupplierIds, $orgStockIds)->groupBy('org_supplier_id');
        $openOrdersBySupplier = $move->purchaseOrdersTakingMovedLinesQuery($purchaseOrder)->whereIn('parent_id', $orgSupplierIds)->get()->groupBy('parent_id');
        $takenReferences      = $move->takenReferencesForMovedLines($purchaseOrder);

        return $orgSuppliers
            ->sortBy(fn (OrgSupplier $orgSupplier) => $orgSupplier->supplier->code)
            ->map(function (OrgSupplier $orgSupplier) use ($purchaseOrder, $purchaseOrderTransactions, $move, $productsBySupplier, $openOrdersBySupplier, $takenReferences) {
                $supplierProducts   = $productsBySupplier->get($orgSupplier->id, collect());
                $openPurchaseOrders = $openOrdersBySupplier->get($orgSupplier->id, collect())->toBase();
                $products           = $purchaseOrderTransactions->mapWithKeys(function (PurchaseOrderTransaction $transaction) use ($supplierProducts) {
                    $supplierProduct = $supplierProducts->first(fn (OrgSupplierProduct $orgSupplierProduct) => (int) $orgSupplierProduct->org_stock_id === (int) $transaction->org_stock_id)?->supplierProduct;

                    return [$transaction->id => $supplierProduct ? [
                        'code'             => $supplierProduct->code,
                        'cost'             => (float) $supplierProduct->cost,
                        'units_per_carton' => (float) $supplierProduct->units_per_carton,
                    ] : null];
                });

                return [
                    'org_supplier_id'      => $orgSupplier->id,
                    'code'                 => $orgSupplier->supplier->code,
                    'name'                 => $orgSupplier->supplier->name,
                    'currency'             => $orgSupplier->supplier->currency?->code,
                    'reference'            => $move->openPurchaseOrderOf($orgSupplier, $purchaseOrder, $openPurchaseOrders)?->reference ?? $move->referenceForMovedLines($purchaseOrder, $orgSupplier, $takenReferences),
                    'open_purchase_orders' => $openPurchaseOrders->pluck('reference')->all(),
                    'products'             => $products->all(),
                    'products_count'       => $products->filter()->count(),
                ];
            })
            ->values()
            ->all();
    }

    public function rules(): array
    {
        return [
            'transactions'   => ['required', 'array', 'min:1', 'max:200'],
            'transactions.*' => ['integer', 'distinct'],
        ];
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): array
    {
        $this->initialisation($purchaseOrder->organisation, $request);

        $transactionIds            = $this->validatedData['transactions'];
        $purchaseOrderTransactions = $purchaseOrder->purchaseOrderTransactions()->whereIn('id', $transactionIds)->get();
        abort_unless($purchaseOrderTransactions->count() === count($transactionIds), 404);

        return $this->handle($purchaseOrder, $purchaseOrderTransactions);
    }
}
