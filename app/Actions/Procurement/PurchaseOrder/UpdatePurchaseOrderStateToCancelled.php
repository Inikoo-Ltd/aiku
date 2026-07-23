<?php

/*
 * author Arya Permana - Kirin
 * created on 12-11-2024-10h-35m
 * github: https://github.com/KirinZero0
 * copyright 2024
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\Procurement\PurchaseOrder\Traits\HasPurchaseOrderHydrators;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Models\Procurement\PurchaseOrder;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdatePurchaseOrderStateToCancelled extends OrgAction
{
    use AsAction;
    use HasPurchaseOrderHydrators;
    use WithActionUpdate;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function handle(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if ($purchaseOrder->state !== PurchaseOrderStateEnum::SUBMITTED) {
            abort(422, __('Only submitted purchase orders can be cancelled'));
        }

        $purchaseOrder->purchaseOrderTransactions()->update([
            'state'          => PurchaseOrderTransactionStateEnum::CANCELLED,
            'net_amount'     => 0,
            'grp_net_amount' => 0,
            'org_net_amount' => 0,
        ]);

        $purchaseOrder = $this->update($purchaseOrder, [
            'state'         => PurchaseOrderStateEnum::CANCELLED,
            'cancelled_at'  => now(),
            'cost_extra'    => 0,
            'cost_shipping' => 0,
            'cost_duties'   => 0,
            'cost_tax'      => 0,
        ]);

        CalculatePurchaseOrderTotalAmounts::run($purchaseOrder);
        PurchaseOrderHydrateTransactions::dispatch($purchaseOrder);

        $this->purchaseOrderHydrate($purchaseOrder);

        return $purchaseOrder;
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder);
    }

    public function action(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $this->asAction      = true;
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, []);

        return $this->handle($purchaseOrder);
    }

    public function jsonResponse(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchaseOrder);
    }
}
