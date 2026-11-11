<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\Inventory\OrgStockHasOrgSupplierProduct\StoreSupplierProductForOrgStock;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\NotifyBuyerOfAgentStageChange;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToCancelled;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToConfirmed;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToSubmitted;
use App\Actions\Traits\Authorisations\WithPurchaseOrderAgentEditAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The supplier of an order through an agent can not make a product: the agent gives all or part of the line to another
 * supplier of the same agent. The line lands on that supplier's open order in the same state, or on a new one, at the
 * price the buyer agreed; a different supplier price is asked to the buyer with the usual price question.
 */
class MovePurchaseOrderTransactionToSupplier extends OrgAction
{
    use WithPurchaseOrderAgentEditAuthorisation;

    /**
     * @param array{org_supplier_id: int, quantity: float|string, code?: string|null, name?: string|null, cost?: float|string|null, units_per_carton?: float|string|null} $modelData
     *
     * @return array{source: PurchaseOrderTransaction, moved: PurchaseOrderTransaction, source_cancelled: bool, source_empty: bool, deposit_paid: bool}
     */
    public function handle(PurchaseOrderTransaction $purchaseOrderTransaction, array $modelData, ?User $user = null): array
    {
        $result = DB::transaction(function () use ($purchaseOrderTransaction, $modelData, $user) {
            $sourcePurchaseOrder = PurchaseOrder::whereKey($purchaseOrderTransaction->purchase_order_id)->lockForUpdate()->first();
            $purchaseOrderTransaction->refresh();
            $quantity = round((float) $modelData['quantity'], 3);

            $this->ensureMovable($sourcePurchaseOrder, $purchaseOrderTransaction, $quantity);
            $orgSupplier = $this->targetOrgSupplier($sourcePurchaseOrder, (int) $modelData['org_supplier_id']);

            $orgStock           = $purchaseOrderTransaction->orgStock;
            $orgSupplierProduct = $this->orgSupplierProductOfStock($orgSupplier, $orgStock->id)
                ?? StoreSupplierProductForOrgStock::make()->action($orgStock, [
                    'org_supplier_id'  => $orgSupplier->id,
                    'code'             => Arr::get($modelData, 'code') ?: $purchaseOrderTransaction->supplierProduct?->code,
                    'name'             => Arr::get($modelData, 'name') ?: ($purchaseOrderTransaction->supplierProduct?->name ?? $orgStock->name),
                    'cost'             => Arr::get($modelData, 'cost'),
                    'units_per_pack'   => $orgStock->packed_in ?: $purchaseOrderTransaction->supplierProduct?->units_per_pack,
                    'units_per_carton' => Arr::get($modelData, 'units_per_carton') ?: $purchaseOrderTransaction->supplierProduct?->units_per_carton,
                    'cbm'              => $purchaseOrderTransaction->supplierProduct?->cbm,
                ]);

            $supplierProduct = $orgSupplierProduct->supplierProduct;
            // ponytail: agents' suppliers share the agent's currency; a supplier in another currency is refused rather than converted
            if ($purchaseOrderTransaction->supplierProduct && $supplierProduct->currency_id !== $purchaseOrderTransaction->supplierProduct->currency_id) {
                throw ValidationException::withMessages(['org_supplier_id' => __(':supplier sells in another currency: cancel the line and order it from them instead', ['supplier' => $orgSupplier->supplier->name])]);
            }

            $agreedUnitCost      = (float) ($purchaseOrderTransaction->unit_cost ?? $supplierProduct->cost);
            $targetPurchaseOrder = $this->openPurchaseOrderOf($orgSupplier, $sourcePurchaseOrder);
            $isNewPurchaseOrder  = $targetPurchaseOrder === null;

            if ($isNewPurchaseOrder) {
                $targetPurchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, [
                    'reference'   => $this->referenceForMovedLines($sourcePurchaseOrder, $orgSupplier),
                    'buyer_id'    => $sourcePurchaseOrder->buyer_id,
                    'currency_id' => $sourcePurchaseOrder->currency_id,
                ], strict: false);
            }

            $moved = StorePurchaseOrderTransaction::make()->action(
                $targetPurchaseOrder,
                $supplierProduct->historicSupplierProduct,
                $orgStock,
                array_filter([
                    'quantity_ordered'        => $quantity,
                    'net_amount'              => round($agreedUnitCost * $quantity, 2),
                    'org_supplier_product_id' => $orgSupplierProduct->id,
                    'state'                   => $isNewPurchaseOrder ? null : PurchaseOrderTransactionStateEnum::from($targetPurchaseOrder->state->value),
                ], fn ($value) => $value !== null),
                strict: false
            );

            if ($isNewPurchaseOrder) {
                // ponytail: handle(), not action(): the packing spec check would stop a product created a moment ago
                $targetPurchaseOrder = UpdatePurchaseOrderStateToSubmitted::make()->handle($targetPurchaseOrder);
                if ($sourcePurchaseOrder->state === PurchaseOrderStateEnum::CONFIRMED) {
                    $targetPurchaseOrder = UpdatePurchaseOrderStateToConfirmed::make()->handle($targetPurchaseOrder);
                }
            }

            if (round((float) $supplierProduct->cost, 6) !== round($agreedUnitCost, 6) && (float) $supplierProduct->cost > 0) {
                $moved = AskPurchaseOrderTransactionPriceChange::make()->action($moved->refresh(), [
                    'unit_cost' => $supplierProduct->cost,
                    'note'      => __('Moved from :reference', ['reference' => $sourcePurchaseOrder->reference]),
                ], $user);
            }

            $isWholeLine = $quantity >= (float) $purchaseOrderTransaction->quantity_ordered;
            $source      = $isWholeLine
                ? CancelPurchaseOrderTransaction::make()->action($purchaseOrderTransaction)
                : UpdatePurchaseOrderTransaction::make()->action($purchaseOrderTransaction, ['quantity_ordered' => round((float) $purchaseOrderTransaction->quantity_ordered - $quantity, 3)]);

            $movedAt = now()->toIso8601String();
            $source->update(['data' => array_merge($source->data ?? [], ['moved_to' => array_merge($source->data['moved_to'] ?? [], [[
                'purchase_order_id'             => $targetPurchaseOrder->id,
                'reference'                     => $targetPurchaseOrder->reference,
                'purchase_order_transaction_id' => $moved->id,
                'supplier_code'                 => $orgSupplier->supplier->code,
                'quantity'                      => $quantity,
                'by'                            => $user?->id,
                'at'                            => $movedAt,
            ]])])]);
            $moved->update(['data' => array_merge($moved->data ?? [], ['moved_from' => [
                'purchase_order_id'             => $sourcePurchaseOrder->id,
                'reference'                     => $sourcePurchaseOrder->reference,
                'purchase_order_transaction_id' => $source->id,
                'supplier_code'                 => $sourcePurchaseOrder->parent_code,
                'quantity'                      => $quantity,
                'by'                            => $user?->id,
                'at'                            => $movedAt,
            ]])]);

            $sourcePurchaseOrder->refresh();
            $isSourceEmpty   = $sourcePurchaseOrder->purchaseOrderTransactions()->whereIn('state', [PurchaseOrderTransactionStateEnum::SUBMITTED, PurchaseOrderTransactionStateEnum::CONFIRMED])->doesntExist();
            $depositPaid     = $sourcePurchaseOrder->depositPayments()->exists();
            $sourceCancelled = $isSourceEmpty && !$depositPaid && $this->cancelWhenEmpty($sourcePurchaseOrder);

            return [
                'source'           => $source->refresh(),
                'moved'            => $moved->refresh(),
                'source_cancelled' => $sourceCancelled,
                'source_empty'     => $isSourceEmpty,
                'deposit_paid'     => $depositPaid,
            ];
        });

        return $result;
    }

    /**
     * The same lines a cancellation accepts: submitted or confirmed, not on a stock delivery, supplier not making them yet.
     */
    public static function isMovable(PurchaseOrderTransaction $purchaseOrderTransaction): bool
    {
        $purchaseOrder = $purchaseOrderTransaction->purchaseOrder;

        return $purchaseOrder !== null
            && $purchaseOrder->isAgentOrder()
            && in_array($purchaseOrder->state, [PurchaseOrderStateEnum::SUBMITTED, PurchaseOrderStateEnum::CONFIRMED], true)
            && in_array($purchaseOrderTransaction->state, [PurchaseOrderTransactionStateEnum::SUBMITTED, PurchaseOrderTransactionStateEnum::CONFIRMED], true)
            && !$purchaseOrder->areLinesLockedByProduction()
            && $purchaseOrderTransaction->org_stock_id !== null
            && !self::isOnDelivery($purchaseOrderTransaction);
    }

    private static function isOnDelivery(PurchaseOrderTransaction $purchaseOrderTransaction): bool
    {
        return PurchaseOrderTransaction::whereKey($purchaseOrderTransaction->id)
            ->whereExists(fn (Builder $items) => StoreStockDeliveryFromPurchaseOrder::liveDeliveryItemsOfTransaction($items))
            ->exists();
    }

    private function ensureMovable(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $purchaseOrderTransaction, float $quantity): void
    {
        $purchaseOrderTransaction->setRelation('purchaseOrder', $purchaseOrder);

        if (!self::isMovable($purchaseOrderTransaction)) {
            throw ValidationException::withMessages(['purchase_order_transaction' => __('Only items not yet in production nor on a stock delivery can be moved to another supplier')]);
        }

        if ($quantity <= 0 || $quantity > (float) $purchaseOrderTransaction->quantity_ordered) {
            throw ValidationException::withMessages(['quantity' => __('Move between 1 and :quantity units', ['quantity' => trimDecimalZeros($purchaseOrderTransaction->quantity_ordered)])]);
        }
    }

    private function targetOrgSupplier(PurchaseOrder $purchaseOrder, int $orgSupplierId): OrgSupplier
    {
        $orgSupplier = OrgSupplier::where('organisation_id', $purchaseOrder->organisation_id)->find($orgSupplierId);

        if (!$orgSupplier || $orgSupplier->id === $purchaseOrder->parent_id || $orgSupplier->orgAgent?->agent_id !== $purchaseOrder->agent_id) {
            throw ValidationException::withMessages(['org_supplier_id' => __('Choose another supplier of the same agent')]);
        }

        return $orgSupplier;
    }

    public static function orgSupplierProductOfStock(OrgSupplier $orgSupplier, int $orgStockId): ?OrgSupplierProduct
    {
        return OrgSupplierProduct::where('org_supplier_id', $orgSupplier->id)
            ->where('state', OrgSupplierProductStateEnum::ACTIVE)
            ->where('is_available', true)
            ->whereExists(fn (Builder $query) => $query->from('org_stock_has_org_supplier_products')
                ->whereColumn('org_stock_has_org_supplier_products.org_supplier_product_id', 'org_supplier_products.id')
                ->where('org_stock_has_org_supplier_products.org_stock_id', $orgStockId))
            ->orderBy('id')
            ->first();
    }

    /**
     * The supplier's order in the same state that can still take lines: same currency, not in production, nothing on a delivery.
     */
    private function openPurchaseOrderOf(OrgSupplier $orgSupplier, PurchaseOrder $sourcePurchaseOrder): ?PurchaseOrder
    {
        return $orgSupplier->purchaseOrders()
            ->where('state', $sourcePurchaseOrder->state)
            ->where('agent_id', $sourcePurchaseOrder->agent_id)
            ->where('currency_id', $sourcePurchaseOrder->currency_id)
            ->get()
            ->filter(fn (PurchaseOrder $purchaseOrder) => !$purchaseOrder->hasProductionStarted()
                && $purchaseOrder->stockDeliveries()->where('stock_deliveries.state', '!=', StockDeliveryStateEnum::CANCELLED)->doesntExist())
            ->sortBy('id')
            ->first();
    }

    /**
     * The source reference with the new supplier's code, the way buyers split agent orders by hand (AWChina-94-SK-KEL):
     * a line already moved once swaps the previous supplier's code instead of stacking codes.
     */
    public function referenceForMovedLines(PurchaseOrder $sourcePurchaseOrder, OrgSupplier $orgSupplier): string
    {
        $supplierCode     = $orgSupplier->supplier->code;
        $sourceParentCode = (string) $sourcePurchaseOrder->parent_code;
        $baseReference    = $sourceParentCode !== ''
            ? preg_replace('/-'.preg_quote($sourceParentCode, '/').'$/i', '', $sourcePurchaseOrder->reference)
            : $sourcePurchaseOrder->reference;

        $reference = $baseReference.'-'.$supplierCode;
        $copy      = 1;
        while (PurchaseOrder::where('organisation_id', $sourcePurchaseOrder->organisation_id)->whereRaw('lower(reference) = ?', [mb_strtolower($reference)])->exists()) {
            $copy++;
            $reference = $baseReference.'-'.$supplierCode.'-'.$copy;
        }

        return $reference;
    }

    /**
     * An order left with nothing to buy is cancelled, unless money was paid on it: then it stays open for the buyer to recover it.
     */
    private function cancelWhenEmpty(PurchaseOrder $purchaseOrder): bool
    {
        if (!in_array($purchaseOrder->state, [PurchaseOrderStateEnum::SUBMITTED, PurchaseOrderStateEnum::CONFIRMED], true)
            || $purchaseOrder->stockDeliveries()->where('stock_deliveries.state', '!=', StockDeliveryStateEnum::CANCELLED)->exists()) {
            return false;
        }

        UpdatePurchaseOrderStateToCancelled::make()->handle($purchaseOrder, source: UpdatePurchaseOrderStateToCancelled::SOURCE_MOVED);

        return true;
    }

    public function rules(): array
    {
        return [
            'org_supplier_id'  => ['required', 'integer'],
            'quantity'         => ['required', 'numeric', 'gt:0'],
            'code'             => ['sometimes', 'nullable', 'string', 'max:64'],
            'name'             => ['sometimes', 'nullable', 'string', 'max:255'],
            'cost'             => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'units_per_carton' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
        ];
    }

    public function action(PurchaseOrderTransaction $purchaseOrderTransaction, array $modelData, ?User $user = null): array
    {
        $this->asAction = true;
        $this->initialisation($purchaseOrderTransaction->organisation, $modelData);

        return $this->handle($purchaseOrderTransaction, $this->validatedData, $user);
    }

    public function asController(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $purchaseOrderTransaction, ActionRequest $request): RedirectResponse
    {
        abort_unless($purchaseOrderTransaction->purchase_order_id === $purchaseOrder->id, 404);
        $this->initialisation($purchaseOrderTransaction->organisation, $request);

        $result = $this->handle($purchaseOrderTransaction, $this->validatedData, $request->user());
        $moved  = $result['moved'];
        $code   = $moved->supplierProduct?->code ?? $moved->orgStock?->code;

        NotifyBuyerOfAgentStageChange::run($purchaseOrder, $request->user(), fn () => implode(' ', array_filter([
            __('the agent moved :quantity units of :code to :supplier (:reference), the supplier can not make them.', [
                'quantity'  => trimDecimalZeros($moved->quantity_ordered),
                'code'      => $code,
                'supplier'  => $moved->purchaseOrder->parent_name,
                'reference' => $moved->purchaseOrder->reference,
            ]),
            $moved->price_query_state ? __('New supplier price :new, was :old: answer it on :reference.', [
                'new'       => trimDecimalZeros($moved->price_query_unit_cost).' '.$moved->purchaseOrder->currency?->code,
                'old'       => trimDecimalZeros($moved->price_query_previous_unit_cost).' '.$moved->purchaseOrder->currency?->code,
                'reference' => $moved->purchaseOrder->reference,
            ]) : null,
            $result['source_cancelled'] ? __('This order had nothing left and was cancelled.') : null,
            $result['source_empty'] && $result['deposit_paid']
                ? __('This order has nothing left but a deposit was paid on it: recover it before cancelling the order.') : null,
        ])));

        return back();
    }
}
