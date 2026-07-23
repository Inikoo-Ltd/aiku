<?php

/*
 * author Arya Permana - Kirin
 * created on 12-11-2024-10h-37m
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

class UpdatePurchaseOrderStateToConfirmed extends OrgAction
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
            abort(422, __('Purchase order can only be confirmed if it is submitted'));
        }

        $purchaseOrder->purchaseOrderTransactions()
            ->where('state', PurchaseOrderTransactionStateEnum::SUBMITTED)
            ->update([
                'state' => PurchaseOrderTransactionStateEnum::CONFIRMED,
            ]);

        $purchaseOrder = $this->update($purchaseOrder, [
            'state'        => PurchaseOrderStateEnum::CONFIRMED,
            'confirmed_at' => now(),
        ]);

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
        $this->asAction = true;
        $this->initialisation($purchaseOrder->organisation, []);

        return $this->handle($purchaseOrder);
    }

    public function jsonResponse(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchaseOrder);
    }
}
