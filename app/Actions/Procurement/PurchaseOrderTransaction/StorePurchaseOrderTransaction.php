<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:26:37 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\Procurement\OrgPartner\GetPartnerSellingProduct;
use App\Actions\Procurement\OrgSupplierProducts\ResolveOrgStockForSupplierProduct;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\CalculatePurchaseOrderTotalAmounts;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithStoreProcurementOrderItem;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\HistoricSupplierProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Procurement\OrgPartner\GetPartnerBuyingPriceFactor;
use App\Actions\Procurement\OrgPartner\GetPartnerLandedCost;

class StorePurchaseOrderTransaction extends OrgAction
{
    use WithProcurementEditAuthorisation;
    use WithNoStrictRules;
    use WithStoreProcurementOrderItem;

    private OrgStock $orgStock;

    /**
     * Set when adding many lines at once: the caller totals and hydrates the order once at the end.
     */
    public bool $batched = false;

    public function handle(PurchaseOrder $purchaseOrder, ?HistoricSupplierProduct $historicSupplierProduct, OrgStock $orgStock, array $modelData): PurchaseOrderTransaction
    {
        $modelData = $this->prepareProcurementOrderItem($purchaseOrder, $historicSupplierProduct, $orgStock, $modelData);

        /** @var PurchaseOrderTransaction $purchaseOrderTransaction */
        $purchaseOrderTransaction = $purchaseOrder->purchaseOrderTransactions()->create($modelData);

        if (!$this->batched) {
            CalculatePurchaseOrderTotalAmounts::run($purchaseOrder);
            PurchaseOrderHydrateTransactions::dispatch($purchaseOrder)->delay($this->hydratorsDelay);
        }

        return $purchaseOrderTransaction;
    }

    public function rules(): array
    {
        $rules = [
            'quantity_ordered' => ['required', 'numeric', 'min:0'],
        ];

        if (! $this->strict) {
            $rules['state'] = ['sometimes', 'required', Rule::enum(PurchaseOrderTransactionStateEnum::class)];
            $rules['delivery_state'] = ['sometimes', 'required', Rule::enum(PurchaseOrderTransactionDeliveryStateEnum::class)];
            $rules['submitted_at'] = ['sometimes', 'required', 'date'];
            $rules['net_amount'] = ['sometimes', 'numeric'];
            $rules['org_exchange'] = ['sometimes', 'numeric'];
            $rules['grp_exchange'] = ['sometimes', 'numeric'];
            $rules['agent_supplier_purchase_order_id'] = ['sometimes', 'nullable', 'integer', 'exists:agent_supplier_purchase_orders,id'];

            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->strict || !isset($this->orgStock)) {
            return;
        }

        if (in_array($this->orgStock->state, [OrgStockStateEnum::DISCONTINUING, OrgStockStateEnum::DISCONTINUED])) {
            $validator->errors()->add('org_stock', __('SKO :code is :state and cannot be ordered', [
                'code'  => $this->orgStock->code,
                'state' => $this->orgStock->state->labels()[$this->orgStock->state->value],
            ]));
        }
    }

    public function action(PurchaseOrder $purchaseOrder, ?HistoricSupplierProduct $historicSupplierProduct, OrgStock $orgStock, array $modelData, int $hydratorsDelay = 0, bool $strict = true): PurchaseOrderTransaction
    {
        $this->orgStock = $orgStock;
        $this->asAction = true;
        $this->strict = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($purchaseOrder->organisation, $modelData);

        return $this->handle($purchaseOrder, $historicSupplierProduct, $orgStock, $this->validatedData);
    }

    public function asController(PurchaseOrder $purchaseOrder, OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): void
    {
        $this->initialisation($purchaseOrder->organisation, $request);

        $this->addOrgSupplierProduct($purchaseOrder, $orgSupplierProduct, $this->validatedData);
    }

    public function inPartnerPurchaseOrder(PurchaseOrder $purchaseOrder, OrgStock $orgStock, ActionRequest $request): void
    {
        $this->initialisation($purchaseOrder->organisation, $request);

        $this->addPartnerOrgStock($purchaseOrder, $orgStock, $this->validatedData);
    }

    /**
     * A partner purchase order is priced at what the partner sells the SKO for; the invoice the
     * partner sends is what costs the delivery in the end.
     *
     * @param  array<string, mixed>  $modelData
     * @throws ValidationException
     */
    public function addPartnerOrgStock(PurchaseOrder $purchaseOrder, OrgStock $orgStock, array $modelData): PurchaseOrderTransaction
    {
        return DB::transaction(function () use ($purchaseOrder, $orgStock, $modelData) {
            $fail = fn (string $message) => throw ValidationException::withMessages(['org_stock' => $message]);

            if (!$purchaseOrder->parent instanceof OrgPartner) {
                $fail(__('Only a purchase order to a partner takes SKOs directly'));
            }
            if ($purchaseOrder->state !== PurchaseOrderStateEnum::IN_PROCESS) {
                $fail(__('Products can only be added while the purchase order is in process'));
            }
            if ($orgStock->organisation_id !== $purchaseOrder->organisation_id) {
                $fail(__('This SKO belongs to another organisation'));
            }
            if (in_array($orgStock->state, [OrgStockStateEnum::DISCONTINUING, OrgStockStateEnum::DISCONTINUED])) {
                $fail(__('SKO :code is :state and cannot be ordered', ['code' => $orgStock->code, 'state' => $orgStock->state->labels()[$orgStock->state->value]]));
            }
            if ($purchaseOrder->purchaseOrderTransactions()->where('org_stock_id', $orgStock->id)->exists()) {
                $fail(__(':code is already on this purchase order, change its quantity instead', ['code' => $orgStock->code]));
            }

            $product   = GetPartnerSellingProduct::run($purchaseOrder->parent, $orgStock->stock_id);
            $unitPrice = $product ? GetPartnerSellingProduct::make()->unitPrice($product) : null;
            if ($unitPrice === null) {
                $fail(__(':partner does not sell :code', ['partner' => $purchaseOrder->parent->partner->name, 'code' => $orgStock->code]));
            }
            $unitPrice = $this->partnerLandedUnitCost($purchaseOrder->parent, $orgStock) ?? $unitPrice * GetPartnerBuyingPriceFactor::run($purchaseOrder->parent);

            return $this->handle($purchaseOrder, null, $orgStock, array_merge($modelData, [
                'unit_cost'  => round($unitPrice, 6),
                'net_amount' => round($unitPrice * (float) $modelData['quantity_ordered'], 2),
            ]));
        });
    }

    public function partnerLandedUnitCost(OrgPartner $orgPartner, OrgStock $orgStock): ?float
    {
        if (!GetPartnerLandedCost::appliesTo($orgPartner)) {
            return null;
        }

        $sellerOrgStock = OrgStock::where('organisation_id', $orgPartner->partner_id)->where('stock_id', $orgStock->stock_id)->orderByRaw("state = 'discontinued'")->orderBy('id')->first();
        if (!$sellerOrgStock || (float) $sellerOrgStock->packed_in <= 0) {
            return null;
        }

        $perSko = GetPartnerLandedCost::run([$sellerOrgStock->id])[$sellerOrgStock->id] ?? null;

        return $perSko === null ? null : $perSko / (float) $sellerOrgStock->packed_in;
    }

    /**
     * @param  array<string, mixed>  $modelData
     * @throws ValidationException
     */
    public function addOrgSupplierProduct(PurchaseOrder $purchaseOrder, OrgSupplierProduct $orgSupplierProduct, array $modelData): PurchaseOrderTransaction
    {
        return DB::transaction(function () use ($purchaseOrder, $orgSupplierProduct, $modelData) {
            $this->ensureCanBeAdded($purchaseOrder, $orgSupplierProduct);
            $orgStock = $this->resolveOrgStock($purchaseOrder, $orgSupplierProduct);

            return $this->handle(
                $purchaseOrder,
                $orgSupplierProduct->supplierProduct->historicSupplierProduct,
                $orgStock,
                array_merge($modelData, ['org_supplier_product_id' => $orgSupplierProduct->id])
            );
        });
    }

    /**
     * @throws ValidationException
     */
    private function ensureCanBeAdded(PurchaseOrder $purchaseOrder, OrgSupplierProduct $orgSupplierProduct): void
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['org_supplier_product' => $message]);

        if ($purchaseOrder->state !== PurchaseOrderStateEnum::IN_PROCESS) {
            $fail(__('Products can only be added while the purchase order is in process'));
        }

        if ($orgSupplierProduct->organisation_id !== $purchaseOrder->organisation_id) {
            $fail(__('This product is not supplied to this organisation'));
        }

        $belongsToParent = match ($purchaseOrder->parent_type) {
            'OrgSupplier' => $orgSupplierProduct->org_supplier_id === $purchaseOrder->parent_id,
            'OrgAgent'    => $orgSupplierProduct->org_agent_id === $purchaseOrder->parent_id,
            default       => true,
        };
        if (!$belongsToParent) {
            $fail(__('This product is not supplied by :parent', ['parent' => $purchaseOrder->parent_name]));
        }

        if ($orgSupplierProduct->state !== OrgSupplierProductStateEnum::ACTIVE->value) {
            $fail(__(':code is not active for this supplier', ['code' => $orgSupplierProduct->supplierProduct->code]));
        }

        if (!$orgSupplierProduct->supplierProduct->historicSupplierProduct) {
            $fail(__(':code has no price history, save the supplier product again', ['code' => $orgSupplierProduct->supplierProduct->code]));
        }

        if ($purchaseOrder->purchaseOrderTransactions()->where('org_supplier_product_id', $orgSupplierProduct->id)->exists()) {
            $fail(__(':code is already on this purchase order, change its quantity instead', ['code' => $orgSupplierProduct->supplierProduct->code]));
        }
    }

    /**
     * @throws ValidationException
     */
    private function resolveOrgStock(PurchaseOrder $purchaseOrder, OrgSupplierProduct $orgSupplierProduct): OrgStock
    {
        $orgStock = ResolveOrgStockForSupplierProduct::run($purchaseOrder->organisation, $orgSupplierProduct->supplierProduct);

        if (!$orgStock) {
            throw ValidationException::withMessages(['org_supplier_product' => __(':code cannot be ordered: its SKO is discontinued, or it is not linked to any SKO', ['code' => $orgSupplierProduct->supplierProduct->code])]);
        }

        if (in_array($orgStock->state, [OrgStockStateEnum::DISCONTINUING, OrgStockStateEnum::DISCONTINUED])) {
            throw ValidationException::withMessages(['org_stock' => __('SKO :code is :state and cannot be ordered', [
                'code'  => $orgStock->code,
                'state' => $orgStock->state->labels()[$orgStock->state->value],
            ])]);
        }

        return $orgStock;
    }

}
