<?php

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\CalculatePurchaseOrderTotalAmounts;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Http\Resources\Procurement\PurchaseOrderTransactionResource;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Lorisleiva\Actions\ActionRequest;

class CancelPurchaseOrderTransaction extends OrgAction
{
    use WithActionUpdate;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function handle(PurchaseOrderTransaction $purchaseOrderTransaction): PurchaseOrderTransaction
    {
        if ($purchaseOrderTransaction->state !== PurchaseOrderTransactionStateEnum::SUBMITTED) {
            abort(422, __('Only submitted items can be cancelled'));
        }

        $purchaseOrderTransaction = $this->update($purchaseOrderTransaction, [
            'state'          => PurchaseOrderTransactionStateEnum::CANCELLED,
            'net_amount'     => 0,
            'grp_net_amount' => 0,
            'org_net_amount' => 0,
        ]);

        CalculatePurchaseOrderTotalAmounts::run($purchaseOrderTransaction->purchaseOrder);
        PurchaseOrderHydrateTransactions::dispatch($purchaseOrderTransaction->purchaseOrder)->delay($this->hydratorsDelay);

        return $purchaseOrderTransaction;
    }

    public function asController(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $purchaseOrderTransaction, ActionRequest $request): PurchaseOrderTransaction
    {
        $this->initialisation($purchaseOrderTransaction->organisation, $request);

        return $this->handle($purchaseOrderTransaction);
    }

    public function action(PurchaseOrderTransaction $purchaseOrderTransaction, int $hydratorsDelay = 0): PurchaseOrderTransaction
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisation($purchaseOrderTransaction->organisation, []);

        return $this->handle($purchaseOrderTransaction);
    }

    public function jsonResponse(PurchaseOrderTransaction $purchaseOrderTransaction): PurchaseOrderTransactionResource
    {
        return new PurchaseOrderTransactionResource($purchaseOrderTransaction);
    }
}
