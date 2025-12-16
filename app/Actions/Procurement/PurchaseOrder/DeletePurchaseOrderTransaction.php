<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 05 May 2023 16:55:25 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeletePurchaseOrderTransaction extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(PurchaseOrderTransaction $purchaseOrderTransaction): void
    {
        $purchaseOrder = $purchaseOrderTransaction->purchaseOrder;
        $purchaseOrderTransaction->delete();
        CalculatePurchaseOrderTotalAmounts::run($purchaseOrder);
        PurchaseOrderHydrateTransactions::dispatch($purchaseOrder);
    }

    public function action(PurchaseOrderTransaction $purchaseOrderTransaction): void
    {
        $this->asAction = true;
        $this->initialisation($purchaseOrderTransaction->organisation, []);
        $this->handle($purchaseOrderTransaction);
    }

    public function asController(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $purchaseOrderTransaction, ActionRequest $request): void
    {
        $this->initialisation($purchaseOrderTransaction->organisation, $request);
        $this->handle($purchaseOrderTransaction);
    }
}
