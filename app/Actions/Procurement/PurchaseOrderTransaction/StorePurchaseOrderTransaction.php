<?php
/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:26:37 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\OrgAction;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStatusEnum;
use App\Models\Procurement\HistoricSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StorePurchaseOrderTransaction extends OrgAction
{
    use AsAction;

    public function handle(PurchaseOrder $purchaseOrder, HistoricSupplierProduct $historicSupplierProduct, array $modelData): PurchaseOrderTransaction
    {
        data_set($modelData, 'group_id', $purchaseOrder->group_id);
        data_set($modelData, 'organisation_id', $purchaseOrder->organisation_id);

        data_set($modelData, 'supplier_product_id', $historicSupplierProduct->supplier_product_id);
        data_set($modelData, 'historic_supplier_product_id', $historicSupplierProduct->id);
        data_set($modelData, 'unit_cost', $historicSupplierProduct->cost);

        /** @var PurchaseOrderTransaction $purchaseOrderTransaction */
        $purchaseOrderTransaction = $purchaseOrder->purchaseOrderTransactions()->create($modelData);

        return $purchaseOrderTransaction;
    }

    public function rules(): array
    {
        $rules = [
            'unit_quantity'    => ['required', 'numeric', 'min:0'],

            'state'               => ['sometimes', Rule::enum(PurchaseOrderTransactionStateEnum::class)],
            'status'              => ['sometimes', Rule::enum(PurchaseOrderTransactionStatusEnum::class)],
            'gross_amount'        => ['sometimes', 'numeric'],
            'net_amount'          => ['sometimes', 'numeric'],
            'org_exchange'        => ['sometimes', 'numeric'],
            'grp_exchange'        => ['sometimes', 'numeric'],
            'org_net_amount'      => ['sometimes', 'numeric'],
            'grp_net_amount'      => ['sometimes', 'numeric'],
            'date'                => ['sometimes', 'required', 'date'],
            'submitted_at'        => ['sometimes', 'required', 'date'],
        ];

        if (!$this->strict) {
            $rules['created_at']      =['sometimes', 'required', 'date'];
            $rules['fetched_at']      =['sometimes', 'required', 'date'];
            $rules['source_id']       =['sometimes', 'string','max:255'];
        }


        return $rules;
    }


    public function action(PurchaseOrder $purchaseOrder, HistoricSupplierProduct $historicSupplierProduct, array $modelData, bool $strict = true): PurchaseOrderTransaction
    {

        $this->strict = $strict;
        $this->initialisation($purchaseOrder->organisation, $modelData);
        return $this->handle($purchaseOrder, $historicSupplierProduct, $this->validatedData);
    }

    public function asController(PurchaseOrder $purchaseOrder, HistoricSupplierProduct $historicSupplierProduct, ActionRequest $request): void
    {
        $this->initialisation($purchaseOrder->organisation, $request);
        $this->handle($purchaseOrder, $historicSupplierProduct, $this->validatedData);
    }
}
