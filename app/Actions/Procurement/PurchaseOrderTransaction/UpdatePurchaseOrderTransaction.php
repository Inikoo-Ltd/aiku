<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 10:48:24 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\CalculatePurchaseOrderTotalAmounts;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\SupplyChain\SupplierProduct\UpdateSupplierProduct;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdatePurchaseOrderTransaction extends OrgAction
{
    use WithProcurementEditAuthorisation;
    use WithActionUpdate;
    use WithNoStrictRules;

    public function handle(PurchaseOrderTransaction $purchaseOrderTransaction, array $modelData): PurchaseOrderTransaction
    {
        $updateSupplierCost = (bool)Arr::pull($modelData, 'update_supplier_cost', false);

        if (Arr::has($modelData, 'net_amount')) {
            $quantity = (float)Arr::get($modelData, 'quantity_ordered', $purchaseOrderTransaction->quantity_ordered);
            if ($quantity > 0) {
                data_set($modelData, 'unit_cost', round(Arr::get($modelData, 'net_amount') / $quantity, 4), overwrite: false);
            }
        } elseif (Arr::hasAny($modelData, ['quantity_ordered', 'unit_cost'])) {
            $unitCost = Arr::get($modelData, 'unit_cost', $purchaseOrderTransaction->unit_cost ?? $purchaseOrderTransaction->supplierProduct?->cost);

            if ($unitCost !== null) {
                data_set($modelData, 'unit_cost', $unitCost);
                data_set($modelData, 'net_amount', $unitCost * Arr::get($modelData, 'quantity_ordered', $purchaseOrderTransaction->quantity_ordered));
            }
        }

        if (Arr::has($modelData, 'net_amount')) {
            data_set($modelData, 'grp_net_amount', Arr::get($modelData, 'net_amount') * ($purchaseOrderTransaction->grp_exchange ?? 1), overwrite: false);
            data_set($modelData, 'org_net_amount', Arr::get($modelData, 'net_amount') * ($purchaseOrderTransaction->org_exchange ?? 1), overwrite: false);
        }

        $purchaseOrderTransaction = $this->update($purchaseOrderTransaction, $modelData, ['data']);
        CalculatePurchaseOrderTotalAmounts::run($purchaseOrderTransaction->purchaseOrder);
        PurchaseOrderHydrateTransactions::dispatch($purchaseOrderTransaction->purchaseOrder)->delay($this->hydratorsDelay);

        if ($updateSupplierCost && $purchaseOrderTransaction->supplierProduct && Arr::has($modelData, 'unit_cost')) {
            UpdateSupplierProduct::make()->action($purchaseOrderTransaction->supplierProduct, ['cost' => $modelData['unit_cost']]);
        }

        return $purchaseOrderTransaction;
    }

    public function rules(): array
    {
        $rules = [
            'quantity_ordered'     => ['sometimes', 'numeric', 'min:0'],
            'unit_cost'            => ['sometimes', 'numeric', 'min:0'],
            'update_supplier_cost' => ['sometimes', 'boolean'],
        ];
        if (! $this->strict) {
            $rules['agent_supplier_purchase_order_id'] = ['sometimes', 'nullable', 'integer', 'exists:agent_supplier_purchase_orders,id'];
            $rules['net_amount'] = ['sometimes', 'numeric'];
            $rules['state'] = ['sometimes', Rule::enum(PurchaseOrderTransactionStateEnum::class)];
            $rules['delivery_state'] = ['sometimes', 'nullable', Rule::enum(PurchaseOrderTransactionDeliveryStateEnum::class)];
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(PurchaseOrderTransaction $purchaseOrderTransaction, array $modelData, int $hydratorsDelay = 0, bool $strict = true): PurchaseOrderTransaction
    {
        $this->asAction = true;
        $this->strict = $strict;

        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisation($purchaseOrderTransaction->organisation, $modelData);

        return $this->handle($purchaseOrderTransaction, $this->validatedData);
    }

    public function asController(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $purchaseOrderTransaction, ActionRequest $request): PurchaseOrderTransaction
    {
        $this->initialisation($purchaseOrderTransaction->organisation, $request);

        return $this->handle($purchaseOrderTransaction, $this->validatedData);
    }

    public function jsonResponse(PurchaseOrderTransaction $purchaseOrderTransaction): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchaseOrderTransaction);
    }
}
