<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-13 08:40
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithPurchaseOrderAgentEditAuthorisation;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Lorisleiva\Actions\ActionRequest;

/**
 * The live items of an order another order can pull, each with the units it can still give and, when the order pulling
 * them has another supplier, that supplier's product for the same SKO.
 */
class GetPurchaseOrderPullSourceLines extends OrgAction
{
    use WithPurchaseOrderAgentEditAuthorisation;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(PurchaseOrder $purchaseOrder, PurchaseOrder $sourcePurchaseOrder): array
    {
        $transactions = $sourcePurchaseOrder->purchaseOrderTransactions()
            ->whereIn('state', [PurchaseOrderTransactionStateEnum::SUBMITTED, PurchaseOrderTransactionStateEnum::CONFIRMED])
            ->with(['supplierProduct', 'orgStock'])
            ->orderBy('id')
            ->get();

        $pullable         = MovePurchaseOrderTransactionToSupplier::pullableQuantities($transactions);
        $isSameSupplier   = $sourcePurchaseOrder->parent_id === $purchaseOrder->parent_id;
        $supplierProducts = $isSameSupplier
            ? collect()
            : MovePurchaseOrderTransactionToSupplier::orgSupplierProductsOfStocks([$purchaseOrder->parent_id], $transactions->pluck('org_stock_id')->filter()->unique()->values()->all());

        return $transactions->map(function (PurchaseOrderTransaction $transaction) use ($sourcePurchaseOrder, $pullable, $isSameSupplier, $supplierProducts) {
            $supplierProduct = $isSameSupplier
                ? null
                : $supplierProducts->first(fn (OrgSupplierProduct $orgSupplierProduct) => (int) $orgSupplierProduct->org_stock_id === (int) $transaction->org_stock_id)?->supplierProduct;

            return [
                'id'                => $transaction->id,
                'code'              => $transaction->supplierProduct?->code ?? $transaction->orgStock?->code,
                'name'              => $transaction->supplierProduct?->name ?? $transaction->orgStock?->name,
                'quantity_ordered'  => (float) $transaction->quantity_ordered,
                'pullable_quantity' => $pullable[$transaction->id]['quantity'],
                'received_at_agent' => $pullable[$transaction->id]['received_at_agent'],
                'blocked_reason'    => $pullable[$transaction->id]['blocked_reason'],
                'stock_delivery'    => $pullable[$transaction->id]['stock_delivery'],
                'unit_cost'         => (float) ($transaction->unit_cost ?? $transaction->supplierProduct?->cost),
                'currency'          => $transaction->currencyCodeIn($sourcePurchaseOrder),
                'units_per_carton'  => $transaction->supplierProduct?->units_per_carton ?? $transaction->orgStock?->packed_in,
                'their_product'     => $supplierProduct ? [
                    'code'             => $supplierProduct->code,
                    'cost'             => (float) $supplierProduct->cost,
                    'units_per_carton' => (float) $supplierProduct->units_per_carton,
                ] : null,
            ];
        })->values()->all();
    }

    public function rules(): array
    {
        return [
            'source' => ['required', 'integer'],
        ];
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): array
    {
        $this->initialisation($purchaseOrder->organisation, $request);

        $sourcePurchaseOrder = MovePurchaseOrderTransactionToSupplier::purchaseOrdersToPullFromQuery($purchaseOrder)->find($this->validatedData['source']);
        abort_unless($sourcePurchaseOrder !== null, 404);

        return $this->handle($purchaseOrder, $sourcePurchaseOrder);
    }
}
