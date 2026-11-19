<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-17 15:01
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\Inventory\OrgStockHasOrgSupplierProduct\StoreSupplierProductForOrgStock;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateState;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydratePurchaseOrders;
use App\Actions\Procurement\PurchaseOrderTransaction\MovePurchaseOrderTransactionToSupplier;
use App\Actions\SupplyChain\Supplier\Hydrators\SupplierHydratePurchaseOrders;
use App\Actions\SupplyChain\SupplierProduct\Hydrators\SupplierProductHydrateLeadTime;
use App\Actions\Traits\Authorisations\WithPurchaseOrderAgentEditAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The goods of an order through an agent are made by another supplier of the same agent than the one the order names
 * (the SKO was only linked to the wrong one): the whole order goes to that supplier, in whatever stage it is, keeping its
 * lines, agreed prices, goods receipts and stages. Each line takes the new supplier's product of its SKO, linked or
 * created at the agreed price when the supplier does not sell it yet. The order either keeps going under the new
 * supplier's code, or joins an order the new supplier already has with the agent ($into): the lines, the goods received,
 * the deposits and the money requested or paid for it go there, and the emptied order is cancelled. Not once items of
 * either order are on a stock delivery.
 *
 * A settled order (Counted & Costed) with no money on it is only corrected: it is re-pointed to the new supplier, never
 * merged, and the stock delivery items received for its lines take the new supplier's product too. Quantities, amounts,
 * costs and stock stay as they were, and so does the stock delivery's own supplier.
 */
class ChangePurchaseOrderSupplier extends OrgAction
{
    use WithPurchaseOrderAgentEditAuthorisation;

    /**
     * @param array{org_supplier_id: int, into_purchase_order_id?: int|null, reference?: string|null, lines?: array<int, array{id: int, code?: string|null, name?: string|null, cost?: float|string|null, units_per_carton?: float|string|null}>} $modelData
     */
    public function handle(PurchaseOrder $purchaseOrder, array $modelData, ?User $user = null): PurchaseOrder
    {
        $oldOrgSupplier = null;
        $newOrgSupplier = null;
        $into           = null;

        $leadTimeSupplierProductIds = [];

        $result = DB::transaction(function () use ($purchaseOrder, $modelData, $user, &$oldOrgSupplier, &$newOrgSupplier, &$into, &$leadTimeSupplierProductIds) {
            $purchaseOrder = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();

            if ($blockedReason = self::blockedReason($purchaseOrder)) {
                throw ValidationException::withMessages(['purchase_order' => $blockedReason]);
            }

            $oldOrgSupplier = $purchaseOrder->parent;
            $newOrgSupplier = self::targetOrgSupplier($purchaseOrder, (int) $modelData['org_supplier_id']);
            $newSupplier    = $newOrgSupplier->supplier;
            $isCorrection   = self::isSettledCorrection($purchaseOrder);

            if ($isCorrection && Arr::get($modelData, 'into_purchase_order_id')) {
                throw ValidationException::withMessages(['into_purchase_order_id' => __('A settled order can only change its supplier, it can not join another order')]);
            }

            $into           = $this->intoPurchaseOrder($purchaseOrder, $newOrgSupplier, Arr::get($modelData, 'into_purchase_order_id'));
            $reference      = $into ? null : (trim((string) Arr::get($modelData, 'reference')) ?: self::referenceFor($purchaseOrder, $newSupplier->code));

            if ($reference !== null && UpdatePurchaseOrderReference::conflictingPurchaseOrder($purchaseOrder, $reference)) {
                throw ValidationException::withMessages(['reference' => __('Another purchase order already uses :reference.', ['reference' => $reference])]);
            }

            $ledgerDatesBefore     = $into ? SyncPurchaseOrderPaidFromAgentLedger::ledgerDatesFor([$into->id]) : [];
            $linesForm             = collect(Arr::get($modelData, 'lines', []))->keyBy('id');
            $changedTransactionIds = [];
            $changedAt             = now()->toIso8601String();
            $deliveryItemsByLine   = $isCorrection ? self::stockDeliveryItemIdsOfLines($purchaseOrder) : [];

            foreach (self::linesToChange($purchaseOrder) as $transaction) {
                $oldSupplierProductId = $transaction->supplier_product_id;
                $orgSupplierProduct = $this->orgSupplierProductFor($newOrgSupplier, $transaction, $linesForm->get($transaction->id, []), $purchaseOrder);
                $supplierProduct    = $orgSupplierProduct->supplierProduct;

                $transaction->update(array_merge([
                    'supplier_product_id'          => $supplierProduct->id,
                    'historic_supplier_product_id' => $supplierProduct->current_historic_supplier_product_id ?? $transaction->historic_supplier_product_id,
                    'org_supplier_product_id'      => $orgSupplierProduct->id,
                    'data'                         => array_merge($transaction->data ?? [], ['supplier_changed_from' => [
                        ...($transaction->data['supplier_changed_from'] ?? []),
                        [
                            'purchase_order_id'       => $purchaseOrder->id,
                            'reference'               => $purchaseOrder->reference,
                            'supplier_product_id'     => $transaction->supplier_product_id,
                            'org_supplier_product_id' => $transaction->org_supplier_product_id,
                            'at'                      => $changedAt,
                        ],
                    ]]),
                ], $into ? [
                    'purchase_order_id'                => $into->id,
                    'state'                            => $into->state->transactionState(),
                    'agent_supplier_purchase_order_id' => $into->agent_supplier_purchase_order_id ?? $transaction->agent_supplier_purchase_order_id,
                ] : []));

                $receivedRows = array_merge(['supplier_product_id' => $supplierProduct->id], $into ? ['purchase_order_id' => $into->id] : []);
                DB::table('agent_warehouse_stocks')->where('purchase_order_transaction_id', $transaction->id)->update($receivedRows);
                DB::table('agent_goods_receipt_items')->where('purchase_order_transaction_id', $transaction->id)->update($receivedRows);

                if ($deliveryItemsByLine[$transaction->id] ?? []) {
                    DB::table('stock_delivery_items')->whereIn('id', $deliveryItemsByLine[$transaction->id])->update([
                        'supplier_product_id'          => $supplierProduct->id,
                        'historic_supplier_product_id' => $supplierProduct->current_historic_supplier_product_id ?? $transaction->historic_supplier_product_id,
                        'org_supplier_product_id'      => $orgSupplierProduct->id,
                    ]);
                }

                if ($isCorrection) {
                    $leadTimeSupplierProductIds[] = $oldSupplierProductId;
                    $leadTimeSupplierProductIds[] = $supplierProduct->id;
                }

                $changedTransactionIds[] = $transaction->id;
            }

            $this->moveGoodsReceiptsWhollyOfSupplier($changedTransactionIds, $newSupplier);

            DB::table('agent_payments')
                ->where('purchase_order_id', $purchaseOrder->id)
                ->where('supplier_id', $oldOrgSupplier->supplier_id)
                ->update(['supplier_id' => $newSupplier->id]);

            $changedFrom = [
                'purchase_order_id' => $purchaseOrder->id,
                'org_supplier_id'   => $oldOrgSupplier->id,
                'supplier_code'     => $oldOrgSupplier->supplier->code,
                'supplier_name'     => $oldOrgSupplier->supplier->name,
                'reference'         => $purchaseOrder->reference,
                'by'                => $user?->id,
                'at'                => $changedAt,
            ];

            if ($isCorrection) {
                $changedFrom['settled_correction'] = [
                    'stock_delivery_item_ids' => array_values(array_merge(...array_values($deliveryItemsByLine ?: [[]]))),
                ];
            }

            if ($into) {
                return $this->mergeInto($purchaseOrder, $into, $changedFrom, $ledgerDatesBefore);
            }

            $this->moveLegacyAgentSupplierOrder($purchaseOrder, $newSupplier);

            $purchaseOrder->parent_id   = $newOrgSupplier->id;
            $purchaseOrder->parent_code = $newSupplier->code;
            $purchaseOrder->parent_name = $newSupplier->name;
            $purchaseOrder->supplier_id = $newSupplier->id;
            $purchaseOrder->reference   = $reference;
            $purchaseOrder->data        = array_merge($purchaseOrder->data ?? [], ['supplier_changed_from' => [
                ...($purchaseOrder->data['supplier_changed_from'] ?? []),
                Arr::except($changedFrom, 'purchase_order_id'),
            ]]);

            if ($purchaseOrder->isDirty('reference')) {
                $purchaseOrder->generateSlug();
            }

            $purchaseOrder->save();

            return $purchaseOrder;
        });

        foreach ([$oldOrgSupplier, $newOrgSupplier] as $orgSupplier) {
            OrgSupplierHydratePurchaseOrders::dispatch($orgSupplier);
            SupplierHydratePurchaseOrders::dispatch($orgSupplier->supplier);
        }

        SupplierProduct::whereIn('id', array_unique(array_filter($leadTimeSupplierProductIds)))
            ->get()
            ->each(fn (SupplierProduct $supplierProduct) => SupplierProductHydrateLeadTime::dispatch($supplierProduct));

        return $result;
    }

    /**
     * The emptied order's money goes with its lines: deposits the buyer recorded, the agent's payments to the supplier,
     * the money requested from the client for it and legacy agent deposits. Its own deposit marks are cleared and kept
     * in merged_into, so the money is counted once, on the order that now holds the goods; a deposit date marked by hand
     * goes there too when that order has none.
     *
     * @param array<int, array{deposit_paid_at: string|null, balance_paid_at: string|null}> $ledgerDatesBefore
     */
    private function mergeInto(PurchaseOrder $purchaseOrder, PurchaseOrder $into, array $changedFrom, array $ledgerDatesBefore): PurchaseOrder
    {
        $movedDepositPayments = DB::table('purchase_order_deposit_payments')->where('purchase_order_id', $purchaseOrder->id)->update(['purchase_order_id' => $into->id]);
        $movedLedgerPayments  = DB::table('agent_payments')->where('purchase_order_id', $purchaseOrder->id)->update(['purchase_order_id' => $into->id]);
        DB::table('deposit_request_items')->where('purchase_order_id', $purchaseOrder->id)->update(['purchase_order_id' => $into->id]);
        DB::table('aspo_deposits')->where('purchase_order_id', $purchaseOrder->id)->update(['purchase_order_id' => $into->id]);

        $depositMarks = Arr::only($purchaseOrder->getAttributes(), ['deposit_amount', 'deposit_paid_at', 'deposit_paid_by_id', 'balance_paid_at']);

        $purchaseOrder->update([
            'deposit_amount'     => null,
            'deposit_paid_at'    => null,
            'deposit_paid_by_id' => null,
            'balance_paid_at'    => null,
            'data'               => array_merge($purchaseOrder->data ?? [], ['merged_into' => [
                'purchase_order_id'    => $into->id,
                'reference'            => $into->reference,
                'deposit_marks'        => $depositMarks,
                'deposit_payments'     => $movedDepositPayments,
                'agent_payments'       => $movedLedgerPayments,
                'by'                   => $changedFrom['by'],
                'at'                   => $changedFrom['at'],
            ]]),
        ]);

        if (in_array($purchaseOrder->state, PurchaseOrderStateEnum::open(), true)) {
            UpdatePurchaseOrderStateToCancelled::make()->handle($purchaseOrder, source: UpdatePurchaseOrderStateToCancelled::SOURCE_MOVED);
        } else {
            CalculatePurchaseOrderTotalAmounts::run($purchaseOrder);
        }

        $into->refresh();
        $into->update(['data' => array_merge($into->data ?? [], ['merged_from' => [
            ...($into->data['merged_from'] ?? []),
            $changedFrom,
        ]])]);

        CalculatePurchaseOrderTotalAmounts::run($into);
        PurchaseOrderHydrateTransactions::dispatch($into)->afterCommit();

        if ($movedDepositPayments > 0) {
            SyncPurchaseOrderDepositPaid::run($into->refresh());
        }

        SyncPurchaseOrderPaidFromAgentLedger::runForOrders($ledgerDatesBefore);

        $into->refresh();
        if ($into->deposit_paid_at === null && $depositMarks['deposit_paid_at'] !== null) {
            $into->update([
                'deposit_paid_at'    => $depositMarks['deposit_paid_at'],
                'deposit_paid_by_id' => $depositMarks['deposit_paid_by_id'],
            ]);
        }

        PurchaseOrderHydrateState::run($into->refresh());

        return $into->refresh();
    }

    private function intoPurchaseOrder(PurchaseOrder $purchaseOrder, OrgSupplier $newOrgSupplier, mixed $intoPurchaseOrderId): ?PurchaseOrder
    {
        if (!$intoPurchaseOrderId) {
            return null;
        }

        $into = self::purchaseOrdersToJoinQuery($purchaseOrder, [$newOrgSupplier->id])->lockForUpdate()->find((int) $intoPurchaseOrderId);

        if (!$into) {
            throw ValidationException::withMessages(['into_purchase_order_id' => __('Choose an open order of :supplier with the same agent and currency, with no items on a stock delivery', ['supplier' => $newOrgSupplier->supplier->name])]);
        }

        return $into;
    }

    /**
     * The orders the lines can join: open orders of those suppliers (sent, never a draft, as money and goods move with
     * the lines) with the same agent and currency, none of their items
     * on a stock delivery.
     *
     * @param array<int, int> $orgSupplierIds
     */
    public static function purchaseOrdersToJoinQuery(PurchaseOrder $purchaseOrder, array $orgSupplierIds): Builder
    {
        return PurchaseOrder::query()
            ->where('organisation_id', $purchaseOrder->organisation_id)
            ->whereKeyNot($purchaseOrder->id)
            ->where('parent_type', 'OrgSupplier')
            ->whereIn('parent_id', $orgSupplierIds)
            ->where('agent_id', $purchaseOrder->agent_id)
            ->where('currency_id', $purchaseOrder->currency_id)
            ->whereIn('state', PurchaseOrderStateEnum::open())
            ->whereDoesntHave('stockDeliveries', fn (Builder $deliveries) => $deliveries->where('stock_deliveries.state', '!=', StockDeliveryStateEnum::CANCELLED));
    }

    public static function canChange(PurchaseOrder $purchaseOrder): bool
    {
        return self::blockedReason($purchaseOrder) === null;
    }

    public static function isSettledCorrection(PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->state === PurchaseOrderStateEnum::SETTLED;
    }

    public static function blockedReason(PurchaseOrder $purchaseOrder): ?string
    {
        return match (true) {
            !$purchaseOrder->isAgentOrder() => __('Only the supplier of an order through an agent can change'),
            self::isSettledCorrection($purchaseOrder) && self::hasMoney($purchaseOrder) => __('This order is settled and has deposits or payments on it: its supplier can no longer change'),
            self::isSettledCorrection($purchaseOrder) => null,
            !in_array($purchaseOrder->state, PurchaseOrderStateEnum::live(), true) => __('This order is :state: its supplier can no longer change', ['state' => PurchaseOrderStateEnum::labels()[$purchaseOrder->state->value] ?? $purchaseOrder->state->value]),
            self::isOnStockDelivery($purchaseOrder) => __('Items of this order are on a stock delivery: take them off before changing the supplier'),
            default => null,
        };
    }

    /**
     * @return Collection<int, PurchaseOrderTransaction>
     */
    public static function linesToChange(PurchaseOrder $purchaseOrder): Collection
    {
        return $purchaseOrder->purchaseOrderTransactions()
            ->where('state', '!=', PurchaseOrderTransactionStateEnum::CANCELLED)
            ->with(['supplierProduct', 'orgStock'])
            ->orderBy('id')
            ->get();
    }

    public static function referenceFor(PurchaseOrder $purchaseOrder, string $newSupplierCode): string
    {
        $oldCode   = (string) $purchaseOrder->parent_code;
        $reference = $purchaseOrder->reference;
        $quoted    = preg_quote($oldCode, '/');

        $base = match (true) {
            $oldCode !== '' && preg_match("/^$quoted-/i", $reference) === 1 => $newSupplierCode.mb_substr($reference, mb_strlen($oldCode)),
            $oldCode !== '' && preg_match("/-$quoted$/i", $reference) === 1 => mb_substr($reference, 0, -mb_strlen($oldCode)).$newSupplierCode,
            default => $reference,
        };

        $candidate = $base;
        $copy      = 1;
        while (UpdatePurchaseOrderReference::conflictingPurchaseOrder($purchaseOrder, $candidate)) {
            $copy++;
            $candidate = "$base-$copy";
        }

        return $candidate;
    }

    public static function targetOrgSupplier(PurchaseOrder $purchaseOrder, int $orgSupplierId): OrgSupplier
    {
        $orgSupplier = OrgSupplier::where('organisation_id', $purchaseOrder->organisation_id)
            ->whereHas('orgAgent', fn ($query) => $query->where('agent_id', $purchaseOrder->agent_id))
            ->with('supplier')
            ->find($orgSupplierId);

        if (!$orgSupplier) {
            throw ValidationException::withMessages(['org_supplier_id' => __('Choose a supplier of the same agent')]);
        }

        if ($orgSupplier->id === $purchaseOrder->parent_id) {
            throw ValidationException::withMessages(['org_supplier_id' => __('The order is already with :supplier', ['supplier' => $orgSupplier->supplier->name])]);
        }

        if ($orgSupplier->supplier->currency_id !== $purchaseOrder->currency_id) {
            throw ValidationException::withMessages(['org_supplier_id' => __(':supplier sells in another currency', ['supplier' => $orgSupplier->supplier->name])]);
        }

        return $orgSupplier;
    }

    /**
     * @param array{code?: string|null, name?: string|null, cost?: float|string|null, units_per_carton?: float|string|null} $lineForm
     */
    private function orgSupplierProductFor(OrgSupplier $orgSupplier, PurchaseOrderTransaction $transaction, array $lineForm, PurchaseOrder $purchaseOrder): OrgSupplierProduct
    {
        $orgStock = $transaction->orgStock;
        $code     = $transaction->supplierProduct?->code ?? $orgStock?->code;

        if (!$orgStock) {
            throw ValidationException::withMessages(["lines.$transaction->id.code" => __(':code has no SKO: it can not get a product of the new supplier', ['code' => $code])]);
        }

        $orgSupplierProduct = MovePurchaseOrderTransactionToSupplier::orgSupplierProductOfStock($orgSupplier, $orgStock->id)
            ?? MovePurchaseOrderTransactionToSupplier::linkUnlinkedOrgSupplierProductOfStock($orgSupplier, $orgStock);

        if ($orgSupplierProduct) {
            if ($orgSupplierProduct->supplierProduct->currency_id !== ($transaction->currency_id ?? $purchaseOrder->currency_id)) {
                throw ValidationException::withMessages(["lines.$transaction->id.code" => __('The product of :supplier for :code is in another currency', ['supplier' => $orgSupplier->supplier->name, 'code' => $code])]);
            }

            return $orgSupplierProduct;
        }

        try {
            return StoreSupplierProductForOrgStock::make()->action($orgStock, [
                'org_supplier_id'  => $orgSupplier->id,
                'code'             => Arr::get($lineForm, 'code') ?: $code,
                'name'             => Arr::get($lineForm, 'name') ?: ($transaction->supplierProduct?->name ?? $orgStock->name),
                'cost'             => Arr::get($lineForm, 'cost') ?? self::agreedUnitCost($transaction),
                'units_per_pack'   => $orgStock->packed_in ?: $transaction->supplierProduct?->units_per_pack,
                'units_per_carton' => Arr::get($lineForm, 'units_per_carton') ?: $transaction->supplierProduct?->units_per_carton,
                'cbm'              => $transaction->supplierProduct?->cbm,
            ]);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $messages, string $field) => ["lines.$transaction->id.$field" => $messages])->all());
        }
    }

    public static function agreedUnitCost(PurchaseOrderTransaction $transaction): float
    {
        return (float) ($transaction->unit_cost ?? $transaction->supplierProduct?->cost ?? 0);
    }

    /**
     * @param array<int, int> $transactionIds
     */
    private function moveGoodsReceiptsWhollyOfSupplier(array $transactionIds, Supplier $newSupplier): void
    {
        if ($transactionIds === []) {
            return;
        }

        $receiptIds = DB::table('agent_goods_receipt_items')->whereIn('purchase_order_transaction_id', $transactionIds)->distinct()->pluck('agent_goods_receipt_id');

        $receiptIdsOfNewSupplierOnly = $receiptIds->reject(fn (int $receiptId) => DB::table('agent_goods_receipt_items')
            ->join('supplier_products', 'supplier_products.id', 'agent_goods_receipt_items.supplier_product_id')
            ->where('agent_goods_receipt_items.agent_goods_receipt_id', $receiptId)
            ->where('supplier_products.supplier_id', '!=', $newSupplier->id)
            ->exists());

        DB::table('agent_goods_receipts')->whereIn('id', $receiptIdsOfNewSupplierOnly->all())->update(['supplier_id' => $newSupplier->id]);
    }

    /**
     * The legacy agent supplier order keeps one row per supplier of an agent order: when the new supplier already has
     * its own row there (its own order in the same container), the row of this order stays as it was, old links still
     * reach this order through purchase_orders.agent_supplier_purchase_order_id.
     */
    private function moveLegacyAgentSupplierOrder(PurchaseOrder $purchaseOrder, Supplier $newSupplier): void
    {
        if (!$purchaseOrder->agent_supplier_purchase_order_id) {
            return;
        }

        $legacyOrder = DB::table('agent_supplier_purchase_orders')->where('id', $purchaseOrder->agent_supplier_purchase_order_id)->first(['id', 'purchase_order_id']);

        if (!$legacyOrder) {
            return;
        }

        $newSupplierHasRow = DB::table('agent_supplier_purchase_orders')
            ->where('purchase_order_id', $legacyOrder->purchase_order_id)
            ->where('supplier_id', $newSupplier->id)
            ->exists();

        if (!$newSupplierHasRow) {
            DB::table('agent_supplier_purchase_orders')->where('id', $legacyOrder->id)->update(['supplier_id' => $newSupplier->id]);
        }
    }

    private static function hasMoney(PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->isDepositPaid()
            || DB::table('agent_payments')->where('purchase_order_id', $purchaseOrder->id)->exists()
            || DB::table('deposit_request_items')->where('purchase_order_id', $purchaseOrder->id)->exists()
            || DB::table('aspo_deposits')->where('purchase_order_id', $purchaseOrder->id)->exists();
    }

    /**
     * The stock delivery items received for each line of a settled order: the items naming the line, and items brought in
     * without an order line (Aurora) on the order's deliveries with the line's SKO and supplier product, taken only when
     * no other order on that delivery has a line of the same supplier product, so another order's item is never re-pointed.
     *
     * @return array<int, array<int, int>>
     */
    public static function stockDeliveryItemIdsOfLines(PurchaseOrder $purchaseOrder): array
    {
        $lines       = self::linesToChange($purchaseOrder);
        $deliveryIds = $purchaseOrder->stockDeliveries()->where('stock_deliveries.state', '!=', StockDeliveryStateEnum::CANCELLED)->pluck('stock_deliveries.id');

        $itemIdsByLine = DB::table('stock_delivery_items')
            ->whereNull('deleted_at')
            ->whereIn(DB::raw("(data->>'purchase_order_transaction_id')::bigint"), $lines->pluck('id'))
            ->get(['id', DB::raw("(data->>'purchase_order_transaction_id')::bigint as purchase_order_transaction_id")])
            ->groupBy('purchase_order_transaction_id')
            ->map(fn (Collection $items) => $items->pluck('id')->all())
            ->all();

        if ($deliveryIds->isEmpty()) {
            return $itemIdsByLine;
        }

        $supplierProductsOnOtherOrders = DB::table('purchase_order_stock_delivery')
            ->join('purchase_order_transactions', 'purchase_order_transactions.purchase_order_id', 'purchase_order_stock_delivery.purchase_order_id')
            ->whereIn('purchase_order_stock_delivery.stock_delivery_id', $deliveryIds)
            ->where('purchase_order_stock_delivery.purchase_order_id', '!=', $purchaseOrder->id)
            ->where('purchase_order_transactions.state', '!=', PurchaseOrderTransactionStateEnum::CANCELLED->value)
            ->whereIn('purchase_order_transactions.supplier_product_id', $lines->pluck('supplier_product_id')->filter())
            ->select('purchase_order_stock_delivery.stock_delivery_id', 'purchase_order_transactions.supplier_product_id')
            ->distinct()
            ->get()
            ->map(fn (object $row) => $row->stock_delivery_id.'-'.$row->supplier_product_id)
            ->flip();

        foreach ($lines as $line) {
            if (isset($itemIdsByLine[$line->id]) || !$line->supplier_product_id || !$line->org_stock_id) {
                continue;
            }

            $legacyItemIds = DB::table('stock_delivery_items')
                ->whereNull('deleted_at')
                ->whereIn('stock_delivery_id', $deliveryIds)
                ->where('org_stock_id', $line->org_stock_id)
                ->where('supplier_product_id', $line->supplier_product_id)
                ->whereRaw("data->>'purchase_order_transaction_id' is null")
                ->get(['id', 'stock_delivery_id'])
                ->reject(fn (object $item) => $supplierProductsOnOtherOrders->has($item->stock_delivery_id.'-'.$line->supplier_product_id))
                ->pluck('id')
                ->all();

            if ($legacyItemIds) {
                $itemIdsByLine[$line->id] = $legacyItemIds;
            }
        }

        return $itemIdsByLine;
    }

    private static function isOnStockDelivery(PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->stockDeliveries()->where('stock_deliveries.state', '!=', StockDeliveryStateEnum::CANCELLED)->exists();
    }

    public function rules(): array
    {
        return [
            'org_supplier_id'          => ['required', 'integer'],
            'into_purchase_order_id'   => ['sometimes', 'nullable', 'integer'],
            'reference'                => ['sometimes', 'nullable', 'string', 'max:255'],
            'lines'                    => ['sometimes', 'array', 'max:500'],
            'lines.*.id'               => ['required', 'integer', 'distinct'],
            'lines.*.code'             => ['sometimes', 'nullable', 'string', 'max:64'],
            'lines.*.name'             => ['sometimes', 'nullable', 'string', 'max:255'],
            'lines.*.cost'             => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lines.*.units_per_carton' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
        ];
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder, $this->validatedData, $request->user());
    }

    public function action(PurchaseOrder $purchaseOrder, array $modelData, ?User $user = null): PurchaseOrder
    {
        $this->asAction = true;
        $this->initialisation($purchaseOrder->organisation, $modelData);

        return $this->handle($purchaseOrder, $this->validatedData, $user);
    }

    public function jsonResponse(PurchaseOrder $purchaseOrder): array
    {
        return [
            'reference' => $purchaseOrder->reference,
            'slug'      => $purchaseOrder->slug,
        ];
    }
}
