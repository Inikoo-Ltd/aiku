<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:26:37 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\CalculatePurchaseOrderTotalAmounts;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithStoreProcurementOrderItem;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\HistoricSupplierProduct;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StorePurchaseOrderTransaction extends OrgAction
{
    use WithNoStrictRules;
    use WithStoreProcurementOrderItem;

    public function handle(PurchaseOrder $purchaseOrder, ?HistoricSupplierProduct $historicSupplierProduct, OrgStock $orgStock, array $modelData): PurchaseOrderTransaction
    {
        $modelData = $this->prepareProcurementOrderItem($purchaseOrder, $historicSupplierProduct, $orgStock, $modelData);

        /** @var PurchaseOrderTransaction $purchaseOrderTransaction */
        $purchaseOrderTransaction = $purchaseOrder->purchaseOrderTransactions()->create($modelData);

        CalculatePurchaseOrderTotalAmounts::run($purchaseOrder);

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

            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function action(PurchaseOrder $purchaseOrder, ?HistoricSupplierProduct $historicSupplierProduct, OrgStock $orgStock, array $modelData, int $hydratorsDelay = 0, bool $strict = true): PurchaseOrderTransaction
    {
        $this->asAction = true;
        $this->strict = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($purchaseOrder->organisation, $modelData);

        return $this->handle($purchaseOrder, $historicSupplierProduct, $orgStock, $this->validatedData);
    }

    public function asController(PurchaseOrder $purchaseOrder, ?HistoricSupplierProduct $historicSupplierProduct, OrgStock $orgStock, ActionRequest $request): void
    {
        $this->initialisation($purchaseOrder->organisation, $request);
        $this->handle($purchaseOrder, $historicSupplierProduct, $orgStock, $this->validatedData);
    }
}
