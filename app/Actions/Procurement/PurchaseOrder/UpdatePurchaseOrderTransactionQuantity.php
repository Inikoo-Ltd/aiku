<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 05 May 2023 16:55:25 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrderTransaction\UpdatePurchaseOrderTransaction;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Lorisleiva\Actions\ActionRequest;

class UpdatePurchaseOrderTransactionQuantity extends OrgAction
{
    public function handle(PurchaseOrderTransaction $purchaseOrderTransaction, array $modelData): PurchaseOrderTransaction
    {
        $purchaseOrderTransaction = UpdatePurchaseOrderTransaction::make()->action($purchaseOrderTransaction, $modelData);

        if ((float) $purchaseOrderTransaction->quantity_ordered <= 0) {
            DeletePurchaseOrderTransaction::run($purchaseOrderTransaction);
        }

        return $purchaseOrderTransaction;
    }

    public function rules(): array
    {
        return [
            'quantity_ordered' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function action(PurchaseOrderTransaction $purchaseOrderTransaction, array $modelData): PurchaseOrderTransaction
    {
        $this->asAction = true;
        $this->initialisation($purchaseOrderTransaction->organisation, $modelData);
        return $this->handle($purchaseOrderTransaction, $modelData);
    }

    public function asController(PurchaseOrderTransaction $purchaseOrderTransaction, ActionRequest $request): PurchaseOrderTransaction
    {
        $this->initialisation($purchaseOrderTransaction->organisation, $request);
        return $this->handle($purchaseOrderTransaction, $this->validatedData);
    }

    public function jsonResponse(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchaseOrder);
    }
}
