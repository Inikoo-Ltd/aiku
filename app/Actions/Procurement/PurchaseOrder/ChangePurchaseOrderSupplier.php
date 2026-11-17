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
use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydratePurchaseOrders;
use App\Actions\Procurement\PurchaseOrderTransaction\MovePurchaseOrderTransactionToSupplier;
use App\Actions\SupplyChain\Supplier\Hydrators\SupplierHydratePurchaseOrders;
use App\Actions\Traits\Authorisations\WithPurchaseOrderAgentEditAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The goods of an order through an agent are made by another supplier of the same agent than the one the order names
 * (the SKO was only linked to the wrong one): the whole order goes to that supplier, in whatever stage it is, keeping its
 * lines, agreed prices, goods receipts and stages. Each line takes the new supplier's product of its SKO, linked or
 * created at the agreed price when the supplier does not sell it yet, and the reference takes the new supplier's code.
 * Not once its items are on a stock delivery, nor with agent payments to the old supplier recorded on it.
 */
class ChangePurchaseOrderSupplier extends OrgAction
{
    use WithPurchaseOrderAgentEditAuthorisation;

    /**
     * @param array{org_supplier_id: int, reference?: string|null, lines?: array<int, array{id: int, code?: string|null, name?: string|null, cost?: float|string|null, units_per_carton?: float|string|null}>} $modelData
     */
    public function handle(PurchaseOrder $purchaseOrder, array $modelData, ?User $user = null): PurchaseOrder
    {
        $oldOrgSupplier = null;
        $newOrgSupplier = null;

        $purchaseOrder = DB::transaction(function () use ($purchaseOrder, $modelData, $user, &$oldOrgSupplier, &$newOrgSupplier) {
            $purchaseOrder = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();

            if ($blockedReason = self::blockedReason($purchaseOrder)) {
                throw ValidationException::withMessages(['purchase_order' => $blockedReason]);
            }

            $oldOrgSupplier = $purchaseOrder->parent;
            $newOrgSupplier = self::targetOrgSupplier($purchaseOrder, (int) $modelData['org_supplier_id']);
            $newSupplier    = $newOrgSupplier->supplier;
            $reference      = trim((string) Arr::get($modelData, 'reference')) ?: self::referenceFor($purchaseOrder, $newSupplier->code);

            if (UpdatePurchaseOrderReference::conflictingPurchaseOrder($purchaseOrder, $reference)) {
                throw ValidationException::withMessages(['reference' => __('Another purchase order already uses :reference.', ['reference' => $reference])]);
            }

            $linesForm             = collect(Arr::get($modelData, 'lines', []))->keyBy('id');
            $changedTransactionIds = [];

            foreach (self::linesToChange($purchaseOrder) as $transaction) {
                $orgSupplierProduct = $this->orgSupplierProductFor($newOrgSupplier, $transaction, $linesForm->get($transaction->id, []), $purchaseOrder);
                $supplierProduct    = $orgSupplierProduct->supplierProduct;

                $transaction->update([
                    'supplier_product_id'          => $supplierProduct->id,
                    'historic_supplier_product_id' => $supplierProduct->current_historic_supplier_product_id ?? $transaction->historic_supplier_product_id,
                    'org_supplier_product_id'      => $orgSupplierProduct->id,
                    'data'                         => array_merge($transaction->data ?? [], ['supplier_changed_from' => [
                        ...($transaction->data['supplier_changed_from'] ?? []),
                        [
                            'supplier_product_id'     => $transaction->supplier_product_id,
                            'org_supplier_product_id' => $transaction->org_supplier_product_id,
                            'at'                      => now()->toIso8601String(),
                        ],
                    ]]),
                ]);

                DB::table('agent_warehouse_stocks')->where('purchase_order_transaction_id', $transaction->id)->update(['supplier_product_id' => $supplierProduct->id]);
                DB::table('agent_goods_receipt_items')->where('purchase_order_transaction_id', $transaction->id)->update(['supplier_product_id' => $supplierProduct->id]);
                $changedTransactionIds[] = $transaction->id;
            }

            $this->moveGoodsReceiptsWhollyOfSupplier($changedTransactionIds, $newSupplier);

            $this->moveLegacyAgentSupplierOrder($purchaseOrder, $newSupplier);

            $purchaseOrder->parent_id   = $newOrgSupplier->id;
            $purchaseOrder->parent_code = $newSupplier->code;
            $purchaseOrder->parent_name = $newSupplier->name;
            $purchaseOrder->supplier_id = $newSupplier->id;
            $purchaseOrder->reference   = $reference;
            $purchaseOrder->data        = array_merge($purchaseOrder->data ?? [], ['supplier_changed_from' => [
                ...($purchaseOrder->data['supplier_changed_from'] ?? []),
                [
                    'org_supplier_id' => $oldOrgSupplier->id,
                    'supplier_code'   => $oldOrgSupplier->supplier->code,
                    'supplier_name'   => $oldOrgSupplier->supplier->name,
                    'reference'       => $purchaseOrder->getOriginal('reference'),
                    'by'              => $user?->id,
                    'at'              => now()->toIso8601String(),
                ],
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

        return $purchaseOrder;
    }

    public static function canChange(PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->isAgentOrder()
            && in_array($purchaseOrder->state, PurchaseOrderStateEnum::live(), true)
            && !self::isOnStockDelivery($purchaseOrder);
    }

    public static function blockedReason(PurchaseOrder $purchaseOrder): ?string
    {
        return match (true) {
            !$purchaseOrder->isAgentOrder() => __('Only the supplier of an order through an agent can change'),
            !in_array($purchaseOrder->state, PurchaseOrderStateEnum::live(), true) => __('This order is :state: its supplier can no longer change', ['state' => PurchaseOrderStateEnum::labels()[$purchaseOrder->state->value] ?? $purchaseOrder->state->value]),
            self::isOnStockDelivery($purchaseOrder) => __('Items of this order are on a stock delivery: take them off before changing the supplier'),
            self::hasPaymentsToSupplier($purchaseOrder) => __('Payments to :supplier are recorded on this order: move them before changing the supplier', ['supplier' => $purchaseOrder->parent_name]),
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

    private static function isOnStockDelivery(PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->stockDeliveries()->where('stock_deliveries.state', '!=', StockDeliveryStateEnum::CANCELLED)->exists();
    }

    private static function hasPaymentsToSupplier(PurchaseOrder $purchaseOrder): bool
    {
        return DB::table('agent_payments')
            ->where('purchase_order_id', $purchaseOrder->id)
            ->where('supplier_id', $purchaseOrder->supplier_id)
            ->exists();
    }

    public function rules(): array
    {
        return [
            'org_supplier_id'          => ['required', 'integer'],
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
