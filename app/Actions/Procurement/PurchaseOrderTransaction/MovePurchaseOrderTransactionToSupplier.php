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
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The supplier of an order through an agent can not make a product: the agent gives all or part of the line to another
 * supplier of the same agent. The line lands on that supplier's open order of the same agent order in the same state, or
 * on a new one named after the source order with the new supplier's code, at the
 * price the buyer agreed; a different supplier price is asked to the buyer with the usual price question.
 */
class MovePurchaseOrderTransactionToSupplier extends OrgAction
{
    use WithPurchaseOrderAgentEditAuthorisation;

    /**
     * @param array{org_supplier_id: int, quantity: float|string, reference?: string|null, code?: string|null, name?: string|null, cost?: float|string|null, units_per_carton?: float|string|null} $modelData
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
            $typedReference      = trim((string) Arr::get($modelData, 'reference'));
            $targetPurchaseOrder = $typedReference !== ''
                ? $this->purchaseOrderNamed($orgSupplier, $sourcePurchaseOrder, $typedReference)
                : $this->openPurchaseOrderOf($orgSupplier, $sourcePurchaseOrder);
            $isNewPurchaseOrder  = $targetPurchaseOrder === null;

            if ($isNewPurchaseOrder) {
                $targetPurchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, [
                    'reference'   => $typedReference !== '' ? $typedReference : $this->referenceForMovedLines($sourcePurchaseOrder, $orgSupplier),
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
                    'state'                   => $isNewPurchaseOrder ? null : $targetPurchaseOrder->state->transactionState(),
                ], fn ($value) => $value !== null),
                strict: false
            );

            if ($isNewPurchaseOrder) {
                // ponytail: handle(), not action(): the packing spec check would stop a product created a moment ago
                $targetPurchaseOrder = UpdatePurchaseOrderStateToSubmitted::make()->handle($targetPurchaseOrder);
                if ($sourcePurchaseOrder->state->isAgreed()) {
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
                ? CancelPurchaseOrderTransaction::make()->action($purchaseOrderTransaction, cancelEmptyOrder: false)
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
            && in_array($purchaseOrder->state, PurchaseOrderStateEnum::open(), true)
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
     * The active, available supplier products of these suppliers for these SKOs, oldest first, each with the org_stock_id it
     * is for: read only, the joined org_stock_id is not a column of the product.
     *
     * @param array<int> $orgSupplierIds
     * @param array<int> $orgStockIds
     *
     * @return Collection<int, OrgSupplierProduct>
     */
    public static function orgSupplierProductsOfStocks(array $orgSupplierIds, array $orgStockIds): Collection
    {
        return OrgSupplierProduct::query()
            ->select('org_supplier_products.*', 'org_stock_has_org_supplier_products.org_stock_id')
            ->join('org_stock_has_org_supplier_products', 'org_stock_has_org_supplier_products.org_supplier_product_id', '=', 'org_supplier_products.id')
            ->whereIn('org_supplier_products.org_supplier_id', $orgSupplierIds)
            ->whereIn('org_stock_has_org_supplier_products.org_stock_id', $orgStockIds)
            ->where('org_supplier_products.state', OrgSupplierProductStateEnum::ACTIVE)
            ->where('org_supplier_products.is_available', true)
            ->with('supplierProduct')
            ->orderBy('org_supplier_products.id')
            ->get();
    }

    /**
     * The supplier's order of the same agent order that can still take lines: one an earlier move from the source order
     * created, or on legacy split orders the supplier's order with the same agent order number. An unrelated open order
     * of that supplier (another container, PPD-AWIndo-132-SK for a line of SET-AWIndo-134-SK) never takes the line.
     */
    public function openPurchaseOrderOf(OrgSupplier $orgSupplier, PurchaseOrder $sourcePurchaseOrder, ?Collection $purchaseOrdersTakingMovedLines = null): ?PurchaseOrder
    {
        $movedLinesReference = mb_strtolower($this->baseReferenceForMovedLines($sourcePurchaseOrder).'-'.$orgSupplier->supplier->code);
        $agentOrderReference = $sourcePurchaseOrder->agent_order_reference ? mb_strtolower($sourcePurchaseOrder->agent_order_reference) : null;

        return ($purchaseOrdersTakingMovedLines ?? $this->purchaseOrdersTakingMovedLines($orgSupplier, $sourcePurchaseOrder))
            ->first(function (PurchaseOrder $purchaseOrder) use ($movedLinesReference, $agentOrderReference) {
                $reference = mb_strtolower($purchaseOrder->reference);

                return $reference === $movedLinesReference
                    || preg_match('/^'.preg_quote($movedLinesReference, '/').'-\d+$/', $reference) === 1
                    || ($agentOrderReference !== null && mb_strtolower((string) $purchaseOrder->agent_order_reference) === $agentOrderReference);
            });
    }

    /**
     * @return Collection<int, PurchaseOrder>
     */
    public function purchaseOrdersTakingMovedLines(OrgSupplier $orgSupplier, PurchaseOrder $sourcePurchaseOrder): Collection
    {
        return $this->purchaseOrdersTakingMovedLinesQuery($sourcePurchaseOrder)->where('parent_id', $orgSupplier->id)->get()->toBase();
    }

    /**
     * Supplier orders in the same state that can still take lines: same agent and currency, not in production,
     * nothing on a delivery. Any of them takes the line when the user types its reference.
     *
     * @return EloquentBuilder<PurchaseOrder>
     */
    public function purchaseOrdersTakingMovedLinesQuery(PurchaseOrder $sourcePurchaseOrder): EloquentBuilder
    {
        return PurchaseOrder::query()
            ->where('organisation_id', $sourcePurchaseOrder->organisation_id)
            ->where('parent_type', 'OrgSupplier')
            ->where('state', $sourcePurchaseOrder->state)
            ->where('agent_id', $sourcePurchaseOrder->agent_id)
            ->where('currency_id', $sourcePurchaseOrder->currency_id)
            ->whereNull('production_started_at')
            ->whereNull('sample_approved_at')
            ->whereNull('produced_at')
            ->whereDoesntHave('stockDeliveries', fn (EloquentBuilder $deliveries) => $deliveries->where('stock_deliveries.state', '!=', StockDeliveryStateEnum::CANCELLED))
            ->orderBy('id');
    }

    /**
     * The order the user named: one of the supplier's orders that can take the line, or null for a new order with that
     * reference. A reference any other order of the organisation already has is refused.
     */
    private function purchaseOrderNamed(OrgSupplier $orgSupplier, PurchaseOrder $sourcePurchaseOrder, string $reference): ?PurchaseOrder
    {
        $lowerReference = mb_strtolower($reference);
        $openOrder      = $this->purchaseOrdersTakingMovedLinesQuery($sourcePurchaseOrder)
            ->where('parent_id', $orgSupplier->id)
            ->whereRaw('lower(reference) = ?', [$lowerReference])
            ->first();

        if ($openOrder) {
            return $openOrder;
        }

        if (PurchaseOrder::where('organisation_id', $sourcePurchaseOrder->organisation_id)->whereRaw('lower(reference) = ?', [$lowerReference])->exists()) {
            throw ValidationException::withMessages(['reference' => __(':reference is already used by an order that can not take this line: type another name', ['reference' => $reference])]);
        }

        return null;
    }

    /**
     * The source reference with the new supplier's code, the way buyers split agent orders by hand (AWChina-94-SK-KEL):
     * a line already moved once swaps the previous supplier's code instead of stacking codes.
     *
     * @param Collection<int, string>|null $takenReferences lower case references starting with the source's, to name many suppliers with one query
     */
    public function referenceForMovedLines(PurchaseOrder $sourcePurchaseOrder, OrgSupplier $orgSupplier, ?Collection $takenReferences = null): string
    {
        $supplierCode    = $orgSupplier->supplier->code;
        $baseReference   = $this->baseReferenceForMovedLines($sourcePurchaseOrder);
        $takenReferences = ($takenReferences ?? $this->takenReferencesForMovedLines($sourcePurchaseOrder))->flip();

        $reference = $baseReference.'-'.$supplierCode;
        $copy      = 1;
        while ($takenReferences->has(mb_strtolower($reference))) {
            $copy++;
            $reference = $baseReference.'-'.$supplierCode.'-'.$copy;
        }

        return $reference;
    }

    /**
     * @return Collection<int, string>
     */
    public function takenReferencesForMovedLines(PurchaseOrder $sourcePurchaseOrder): Collection
    {
        $prefix = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($this->baseReferenceForMovedLines($sourcePurchaseOrder))).'-%';

        return PurchaseOrder::where('organisation_id', $sourcePurchaseOrder->organisation_id)
            ->whereRaw('lower(reference) like ?', [$prefix])
            ->pluck('reference')
            ->map(fn (string $reference) => mb_strtolower($reference))
            ->values();
    }

    private function baseReferenceForMovedLines(PurchaseOrder $sourcePurchaseOrder): string
    {
        $sourceParentCode = (string) $sourcePurchaseOrder->parent_code;

        return $sourceParentCode !== ''
            ? preg_replace('/-'.preg_quote($sourceParentCode, '/').'$/i', '', $sourcePurchaseOrder->reference)
            : $sourcePurchaseOrder->reference;
    }

    /**
     * An order left with nothing to buy is cancelled, unless money was paid on it: then it stays open for the buyer to recover it.
     */
    private function cancelWhenEmpty(PurchaseOrder $purchaseOrder): bool
    {
        if (!in_array($purchaseOrder->state, PurchaseOrderStateEnum::open(), true)
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
            'reference'        => ['sometimes', 'nullable', 'string', 'max:64'],
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
        self::notifyBuyer($purchaseOrder, $request->user(), [$result]);

        return back();
    }

    /**
     * One message to the buyer for lines moved together to the same supplier order.
     *
     * @param array<int, array{source: PurchaseOrderTransaction, moved: PurchaseOrderTransaction, source_cancelled: bool, source_empty: bool, deposit_paid: bool}> $results
     */
    public static function notifyBuyer(PurchaseOrder $purchaseOrder, User $user, array $results): void
    {
        $lastResult  = end($results);
        $targetOrder = $lastResult['moved']->purchaseOrder;
        $currency    = $targetOrder->currency?->code;

        NotifyBuyerOfAgentStageChange::run($purchaseOrder, $user, fn () => implode(' ', array_filter([
            __('the agent moved :lines to :supplier (:reference), the supplier can not make them.', [
                'lines'     => collect($results)->map(fn (array $result) => __(':quantity units of :code', [
                    'quantity' => trimDecimalZeros($result['moved']->quantity_ordered),
                    'code'     => $result['moved']->supplierProduct?->code ?? $result['moved']->orgStock?->code,
                ]))->implode(', '),
                'supplier'  => $targetOrder->parent_name,
                'reference' => $targetOrder->reference,
            ]),
            ...collect($results)->filter(fn (array $result) => $result['moved']->price_query_state)->map(fn (array $result) => __('New supplier price of :code :new, was :old: answer it on :reference.', [
                'code'      => $result['moved']->supplierProduct?->code ?? $result['moved']->orgStock?->code,
                'new'       => trimDecimalZeros($result['moved']->price_query_unit_cost).' '.$currency,
                'old'       => trimDecimalZeros($result['moved']->price_query_previous_unit_cost).' '.$currency,
                'reference' => $targetOrder->reference,
            ]))->values()->all(),
            $lastResult['source_cancelled'] ? __('This order had nothing left and was cancelled.') : null,
            $lastResult['source_empty'] && $lastResult['deposit_paid']
                ? __('This order has nothing left but a deposit was paid on it: recover it before cancelling the order.') : null,
        ])));
    }
}
