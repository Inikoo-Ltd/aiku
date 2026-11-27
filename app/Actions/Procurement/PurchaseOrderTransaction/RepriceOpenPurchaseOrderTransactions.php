<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-27
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionPriceQueryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * A new supplier cost carried to the lines of that supplier product still priced by hand: draft and submitted
 * orders of every organisation, in the supplier product's currency. Agreed orders keep the price agreed with the
 * supplier, and a line with a price request waiting for an answer is answered on its own.
 */
class RepriceOpenPurchaseOrderTransactions
{
    use AsObject;

    /**
     * @return Collection<int, PurchaseOrderTransaction>
     */
    public function handle(SupplierProduct $supplierProduct, float $unitCost, ?User $user = null): Collection
    {
        return self::repriceableLines($supplierProduct, $unitCost, $user)
            ->map(fn (PurchaseOrderTransaction $transaction) => UpdatePurchaseOrderTransaction::make()->action($transaction, ['unit_cost' => $unitCost]))
            ->values();
    }

    /**
     * @return Collection<int, PurchaseOrderTransaction>
     */
    public static function repriceableLines(SupplierProduct $supplierProduct, float $unitCost, ?User $user = null): Collection
    {
        return PurchaseOrderTransaction::query()
            ->where('supplier_product_id', $supplierProduct->id)
            ->whereIn('state', [PurchaseOrderTransactionStateEnum::IN_PROCESS, PurchaseOrderTransactionStateEnum::SUBMITTED])
            ->whereHas('purchaseOrder', fn (Builder $query) => $query->whereIn('state', [PurchaseOrderStateEnum::IN_PROCESS, PurchaseOrderStateEnum::SUBMITTED]))
            ->where(fn (Builder $query) => $query->where('currency_id', $supplierProduct->currency_id)
                ->orWhere(fn (Builder $query) => $query->whereNull('currency_id')->whereHas('purchaseOrder', fn (Builder $query) => $query->where('currency_id', $supplierProduct->currency_id))))
            ->where(fn (Builder $query) => $query->whereNull('price_query_state')->orWhere('price_query_state', '!=', PurchaseOrderTransactionPriceQueryStateEnum::PENDING))
            ->with(['purchaseOrder.organisation'])
            ->orderBy('id')
            ->get()
            ->filter(fn (PurchaseOrderTransaction $transaction) => round((float) $transaction->unit_cost, 6) !== round($unitCost, 6))
            ->filter(fn (PurchaseOrderTransaction $transaction) => $user === null || $user->authTo("procurement.$transaction->organisation_id.edit"))
            ->values();
    }
}
